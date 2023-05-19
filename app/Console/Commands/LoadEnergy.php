<?php

namespace App\Console\Commands;

use App\Enums\EventTypes;
use App\Models\BotUser;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Notifications\EventNotification;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use PHPHtmlParser\Dom;

class LoadEnergy extends Command
{
    protected $signature = 'app:load-energy';

    protected $description = 'Command description';

    public function handle(Client $client, Dom $dom)
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

                $event = Event::query()
                    ->where('service_center_id', $serviceCenter->id)
                    ->where('start', Carbon::createFromFormat('Y-m-d H:i', $rawEvent->disconnectionDate))
                    ->where('finish', Carbon::createFromFormat('Y-m-d H:i', $rawEvent->reconnectionDate))
                    ->where('type', EventTypes::energy)
                    ->first();

                if (!$event) {
                    /* @var $event Event */
                    $event = Event::query()->create([
                        'service_center_id' => $serviceCenter->id,
                        'start' => Carbon::createFromFormat('Y-m-d H:i', $rawEvent->disconnectionDate),
                        'finish' => Carbon::createFromFormat('Y-m-d H:i', $rawEvent->reconnectionDate),
                        'total_addresses' => count($addresses),
                        'type' => EventTypes::energy,
                        'effected_customers' => $rawEvent->scEffectedCustomers,
                    ]);

                    foreach ($addresses as $address) {
                        $addressArray = array_filter([$rawEvent->scName, $rawEvent->regionName, $address],
                            function($item) {
                                return !empty($item);
                            });

                        /* @var $addressObject \App\Models\Address */
                        $addressObject = $serviceCenter->addresses()->firstOrCreate([
                            'name' => implode(', ', $addressArray),
                        ]);
                        $addressObject->events()->syncWithoutDetaching($event);
                    }

                    $botUser = BotUser::query()
                        ->where('id', '411174495')
                        ->first();

                    if ($botUser) {
                        Notification::route('telegram', $botUser->id)
                            ->notify(new EventNotification($event, $botUser->language_code));
                    }
                }
            }
        }
    }
}
