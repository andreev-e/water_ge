<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Support\SourceStatus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class LoadWater extends Command
{
    protected $signature = 'load-schedule';

    protected $description = 'Load water schedule';

    /**
     * water.gov.ge is now a Nuxt SPA; the old #accordion HTML markup is gone.
     * The planned-works list is embedded as the page's Nuxt SSR payload instead.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(Client $client): void
    {
        $response = $client->get('https://water.gov.ge/planned-works');

        if (!preg_match('/<script[^>]*id="__NUXT_DATA__"[^>]*>(.*?)<\/script>/s', $response->getBody()->getContents(), $matches)) {
            $this->error('Could not find __NUXT_DATA__ payload');
            return;
        }

        $payload = json_decode($matches[1], true);

        if (!is_array($payload)) {
            $this->error('Could not decode __NUXT_DATA__ payload');
            return;
        }

        foreach ($this->extractProblems($payload) as $item) {
            if (($item['source'] ?? null) !== 'problem' || empty($item['published_at']) || empty($item['end_date'])) {
                continue;
            }

            $serviceCenterName = trim((string) ($item['contractor'] ?? ''));

            if ($serviceCenterName === '') {
                continue;
            }

            $serviceCenter = $this->resolveServiceCenter($serviceCenterName);
            $addresses = $this->extractAddresses((string) ($item['excerpt'] ?? ''));

            $start = Carbon::createFromFormat('Y-m-d H:i:s', $item['published_at']);
            $finish = Carbon::createFromFormat('Y-m-d H:i:s', $item['end_date']);

            $event = Event::query()
                ->where('service_center_id', $serviceCenter->id)
                ->where('start', $start)
                ->where('finish', $finish)
                ->where('type', EventTypes::water)
                ->first();

            if (!$event) {
                /* @var $event Event */
                $event = Event::query()->create([
                    'service_center_id' => $serviceCenter->id,
                    'start' => $start,
                    'finish' => $finish,
                    'total_addresses' => count($addresses),
                    'type' => EventTypes::water,
                ]);

                foreach ($addresses as $address) {
                    /* @var $addressObject \App\Models\Address */
                    $addressObject = $serviceCenter->addresses()->firstOrCreate(['name' => $address]);
                    $addressObject->events()->syncWithoutDetaching($event);
                }

                $event->notifySubscribed();
            }
        }

        SourceStatus::markUpdated(SourceStatus::WATER);
    }

    /**
     * Resolves the "planned-works-problems" list of items out of the Nuxt
     * __NUXT_DATA__ payload, which stores every value as an index into the
     * flat $data array (devalue format).
     */
    private function extractProblems(array $data): array
    {
        $rootIdx = $this->unwrapRef($data, 0);

        if (!is_int($rootIdx) || !isset($data[$rootIdx]['data'])) {
            return [];
        }

        $dataIdx = $this->unwrapRef($data, $data[$rootIdx]['data']);

        if (!is_int($dataIdx) || !isset($data[$dataIdx]['planned-works-problems'])) {
            return [];
        }

        $problems = $this->resolve($data, $data[$dataIdx]['planned-works-problems']);

        return is_array($problems) ? ($problems['items'] ?? []) : [];
    }

    private function unwrapRef(array $data, mixed $idx): mixed
    {
        if (!is_int($idx) || !array_key_exists($idx, $data)) {
            return $idx;
        }

        $value = $data[$idx];

        if (is_array($value) && array_is_list($value) && count($value) === 2 &&
            is_string($value[0]) && in_array($value[0], ['ShallowReactive', 'Reactive', 'ShallowRef', 'Ref'], true)) {
            return $value[1];
        }

        return $idx;
    }

    private function resolve(array $data, mixed $idx): mixed
    {
        if (!is_int($idx) || !array_key_exists($idx, $data)) {
            return $idx;
        }

        $value = $data[$this->unwrapRef($data, $idx)];

        if (!is_array($value)) {
            return $value;
        }

        return array_map(fn ($item) => $this->resolve($data, $item), $value);
    }

    /**
     * water.gov.ge exposes only a bare municipality name (e.g. "ხობი"), while
     * ServiceCenter names elsewhere carry the full genitive + "სერვის ცენტრი"
     * form (e.g. "ხობის სერვის ცენტრი"). Match against the existing catalog
     * instead of blindly creating a bare-name duplicate.
     */
    private function resolveServiceCenter(string $name): ServiceCenter
    {
        $exact = ServiceCenter::query()->where('name', $name)->first();

        if ($exact) {
            return $exact;
        }

        $stem = mb_substr($name, 0, -1);

        $fuzzy = ServiceCenter::query()
            ->where('name', 'LIKE', $name . '%')
            ->orWhere('name', 'LIKE', $stem . '%')
            ->orderByRaw('LENGTH(name) ASC')
            ->first();

        return $fuzzy ?? ServiceCenter::query()->create(['name' => $name]);
    }

    private function extractAddresses(string $excerpt): array
    {
        if (!preg_match('/მისამართები:\s*(.+)$/u', $excerpt, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
    }
}
