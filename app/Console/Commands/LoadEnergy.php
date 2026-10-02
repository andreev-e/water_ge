<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\BotUser;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Notifications\EventNotification;
use App\Support\SourceStatus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class LoadEnergy extends Command
{
    protected $signature = 'app:load-energy';

    protected $description = 'Command description';

    public function handle(Client $client)
    {
        $url = 'https://my.energo-pro.ge/owback/searchAlerts';
        $response = $client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'json' => [
                'search' => '',
            ],
            'compress' => true,
        ]);
        $data = json_decode($response->getBody()->getContents(), false);

        if ($data->status === 200) {
            foreach ($data->data as $rawEvent) {

                $serviceCenter = ServiceCenter::query()
                    ->where('name', 'LIKE', '%' . mb_substr($rawEvent->scName, 0, -1) . '%')
                    ->first();

                if (!$serviceCenter) {
                    $serviceCenter = ServiceCenter::query()->create(['name' => $rawEvent->scName]);
                }

                $addresses = explode(', ', $rawEvent->disconnectionArea);
                // my.energo-pro.ge labels taskType "1" as planned and any other as unplanned.
                $planned = (string) $rawEvent->taskType === '1';

                $event = Event::query()
                    ->where('service_center_id', $serviceCenter->id)
                    ->where('start', $this->parseDate($rawEvent->disconnectionDate))
                    ->where('finish', $this->parseDate($rawEvent->reconnectionDate))
                    ->where('type', EventTypes::energy)
                    ->first();

                if (!$event) {
                    /* @var $event Event */
                    $event = Event::query()->create([
                        'service_center_id' => $serviceCenter->id,
                        'start' => $this->parseDate($rawEvent->disconnectionDate),
                        'finish' => $this->parseDate($rawEvent->reconnectionDate),
                        'total_addresses' => count($addresses),
                        'type' => EventTypes::energy,
                        'effected_customers' => $rawEvent->scEffectedCustomers,
                        'planned' => $planned,
                    ]);

                    foreach ($addresses as $address) {
                        /* @var $addressObject \App\Models\Address */
                        $addressObject = $serviceCenter->addresses()->firstOrCreate([
                            'name' => $address,
                        ]);
                        $addressObject->events()->syncWithoutDetaching($event);
                    }

                    $event->notifySubscribed();
                    $event->publishToFacebook();
                } elseif ($event->planned === null) {
                    $event->update(['planned' => $planned]);
                }
            }

            SourceStatus::markUpdated(SourceStatus::ENERGY);
        }
    }

    // The API sometimes appends seconds and fractions ("2026-10-02 15:31:03.0000000"); keep minute precision.
    private function parseDate(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', mb_substr($value, 0, 16));
    }
}
