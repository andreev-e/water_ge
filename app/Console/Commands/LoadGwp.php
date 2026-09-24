<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Support\GwpDisconnectParser;
use App\Support\SourceStatus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Tbilisi (and Rustavi) water is served by GWP, not by water.gov.ge,
 * so those outages come from GWP's own public API.
 * gwp.ge is reachable only from Georgian IPs, see services.gwp.proxy.
 */
class LoadGwp extends Command
{
    protected $signature = 'load-gwp';

    protected $description = 'Load GWP (Tbilisi water) disconnects';

    private const ENDPOINTS = [
        'https://www.gwp.ge/api/Disconnect',
        'https://www.gwp.ge/api/Disconnect/ListPlanAsync',
    ];

    private const DEFAULT_SERVICE_CENTER = 'თბილისი';

    public function handle(Client $client): void
    {
        $items = [];

        foreach (self::ENDPOINTS as $url) {
            try {
                $response = $client->get($url, array_filter([
                    'connect_timeout' => 10,
                    'timeout' => 30,
                    'proxy' => config('services.gwp.proxy'),
                    // Prod runs Debian 10 whose CA bundle predates gwp.ge's Sectigo R46 root.
                    'verify' => resource_path('certs/sectigo-r46.pem'),
                ]));
            } catch (GuzzleException $e) {
                $this->error('GWP API request failed: ' . $e->getMessage());
                Log::error('GWP API request failed: ' . $e->getMessage());
                return;
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (!is_array($data)) {
                $this->error('Could not decode GWP response from ' . $url);
                return;
            }

            foreach ($data as $item) {
                if (!empty($item['code'])) {
                    $items[$item['code']] = $item;
                }
            }
        }

        $existingEvents = Event::query()
            ->whereIn('external_id', array_keys($items))
            ->get()
            ->keyBy('external_id');

        foreach ($items as $code => $item) {
            $text = trim((string) ($item['emailText'] ?? $item['address'] ?? ''));
            [$start, $finish] = GwpDisconnectParser::parsePeriod($text);

            if (!$finish) {
                $this->warn('Could not parse GWP period for ' . $code);
                Log::warning('Could not parse GWP period for ' . $code, ['text' => $text]);
                continue;
            }

            /* @var $event Event|null */
            $event = $existingEvents->get($code);

            if ($event) {
                // GWP often extends the restore time; keep it fresh without re-notifying.
                $event->finish = $finish;
                if ($start) {
                    $event->start = $start;
                }
                $event->save();
                continue;
            }

            // Emergency messages carry only the restore time: the outage is already on.
            $start ??= Carbon::now()->startOfMinute();

            if ($finish->lte($start)) {
                $this->warn('GWP event ' . $code . ' finishes before it starts');
                continue;
            }

            $serviceCenter = $this->resolveServiceCenter(trim((string) ($item['district'] ?? '')));
            $addresses = GwpDisconnectParser::parseAddresses($text);

            /* @var $event Event */
            $event = Event::query()->create([
                'service_center_id' => $serviceCenter->id,
                'start' => $start,
                'finish' => $finish,
                'total_addresses' => count($addresses),
                'type' => EventTypes::water,
                'name' => $text,
                'external_id' => $code,
            ]);

            foreach ($addresses as $address) {
                /* @var $addressObject \App\Models\Address */
                $addressObject = $serviceCenter->addresses()->firstOrCreate(['name' => $address]);
                $addressObject->events()->syncWithoutDetaching($event);
            }

            $event->notifySubscribed();
        }

        SourceStatus::markUpdated(SourceStatus::GWP);
    }

    /**
     * GWP "district" is a Tbilisi district (ვაკე, გლდანი...) or a separate
     * city it also serves (რუსთავი). Only the latter has its own ServiceCenter.
     */
    private function resolveServiceCenter(string $district): ServiceCenter
    {
        $serviceCenter = $district !== ''
            ? ServiceCenter::query()->where('name', $district)->first()
            : null;

        return $serviceCenter ?? ServiceCenter::query()->firstOrCreate(['name' => self::DEFAULT_SERVICE_CENTER]);
    }
}
