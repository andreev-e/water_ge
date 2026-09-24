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
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LoadGas extends Command
{
    protected $signature = 'load-gas';

    protected $description = 'Command description';

    public function handle(Client $client)
    {
        $page = 1;
        do {
            $url = 'https://utilixwebapi.azurewebsites.net/api/Outage/GetOutagesWithPaging';

            try {
                $response = $client->get($url, [
                    'query' => [
                        'pageIndex' => $page,
                        'PageSize' => 100,
                    ],
                    'headers' => [
                        'Referer' => 'https://www.mygas.ge/',
                    ],
                    'compress' => true,
                ]);
            } catch (GuzzleException $e) {
                $this->error('Gas outages API request failed: ' . $e->getMessage());
                Log::error('Gas outages API request failed: ' . $e->getMessage());
                return;
            }

            $data = json_decode($response->getBody()->getContents(), false);

            $serviceCenters = ServiceCenter::all();
            $serviceCenterNames = $serviceCenters->mapWithKeys(function (ServiceCenter $serviceCenter) {
                return [$serviceCenter->id => str_replace(
                    array('ს სერვის ცენტრი', 'ს სერვის ცენთრი', 'აბაშა', 'ყვარელი', ' სერვის ცენთრი'),
                    array('', '', 'აბაში', 'ყვარლი', ''),
                    $serviceCenter->name
                )];
            });

            $starts = collect($data->items)
                ->map(fn($item) => Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start));

            $existingEvents = Event::query()
                ->where('type', EventTypes::gas)
                ->whereIn('start', $starts)
                ->get()
                ->keyBy(fn(Event $event) => $event->service_center_id . '|' . $event->start->toDateTimeString() . '|' . $event->finish->toDateTimeString());

            foreach ($data->items as $item) {
                $foundedServiceCenter = null;
                foreach ($serviceCenters as $serviceCenter) {
                    $nameGe = $serviceCenterNames[$serviceCenter->id];
                    if (stripos($item->detail->notificationTitle, $nameGe) ||
                        stripos($item->detail->notificationTitleEN, $serviceCenter->name_en)) {
                        $foundedServiceCenter = $serviceCenter->id;
                    }
                }

                if (!$foundedServiceCenter) {
                    $foundedServiceCenter = $this->findCorrupt($item);
                }

                if (!$foundedServiceCenter) {
                    continue;
                }

                $start = Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start);
                $finish = Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->end);
                $key = $foundedServiceCenter . '|' . $start->toDateTimeString() . '|' . $finish->toDateTimeString();

                if (!$existingEvents->has($key)) {
                    /* @var $event Event */
                    $event = Event::query()->create([
                        'service_center_id' => $foundedServiceCenter,
                        'start' => $start,
                        'finish' => $finish,
                        'total_addresses' => 0,
                        'type' => EventTypes::gas,
                        'name' => $item->detail->notificationTitle,
                        'name_en' => $item->detail->notificationTitleEN,
                    ]);

                    $event->notifySubscribed();
                }
            }

            SourceStatus::markUpdated(SourceStatus::GAS);

            $page++;
            echo $page . PHP_EOL;
            return;
        } while ($data->hasNext);
    }

    private function findCorrupt(mixed $item): ?int
    {
        if (stripos($item->detail->notificationTitle, 'эთეტრიწყაროს') ||
            stripos($item->detail->notificationTitle, 'თეტრიწყაროს')) {
            return 40;
        }

        if (stripos($item->detail->notificationTitle, 'დ უ შეთ ის')) {
            return 20;
        }

        if (stripos($item->detail->notificationTitle, 'სიღნარის')) {
            return 24;
        }

        if (stripos($item->detail->notificationTitle, 'Sagarejo')) {
            return 30;
        }

        if (stripos($item->detail->notificationTitle, 'ბარდათის')) {
            return 42;
        }

        if (stripos($item->detail->notificationTitle, 'Gardabani')) {
            return 68;
        }

        if (stripos($item->detail->notificationTitle, 'მარენეულის')) {
            return 25;
        }

        if (stripos($item->detail->notificationTitle, 'ბორჯმის') ||
            stripos($item->detail->notificationTitle, 'Borjomi')) {
            return 10;
        }

        return null;
    }
}
