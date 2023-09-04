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

class LoadGas extends Command
{
    protected $signature = 'load-gas';

    protected $description = 'Command description';

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(Client $client)
    {
        $page = 1;
        do {
            $url = 'https://utilixwebapi.azurewebsites.net/api/Outage/GetOutagesWithPaging';
            $response = $client->get($url, [
                'query' => [
                    'pageIndex' => $page,
                    'PageSize' => 100,
                ],
                'compress' => true,
            ]);
            $data = json_decode($response->getBody()->getContents(), false);

            $serviceCenters = ServiceCenter::all();
            foreach ($data->items as $item) {
                $foundedServiceCenter = null;
                foreach ($serviceCenters as $serviceCenter) {
                    $nameGe = str_replace(
                        array('ს სერვის ცენტრი', 'ს სერვის ცენთრი', 'აბაშა', 'ყვარელი', ' სერვის ცენთრი'),
                        array('', '', 'აბაში', 'ყვარლი', ''),
                        $serviceCenter->name
                    );
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

                $event = Event::query()
                    ->where('service_center_id', $foundedServiceCenter)
                    ->where('start', Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start))
                    ->where('finish', Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->end))
                    ->where('type', EventTypes::gas)
                    ->first();

                if (!$event) {
                    /* @var $event Event */
                    $event = Event::query()->create([
                        'service_center_id' => $foundedServiceCenter,
                        'start' => Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start),
                        'finish' => Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->end),
                        'total_addresses' => 0,
                        'type' => EventTypes::gas,
                        'name' => $item->detail->notificationTitle,
                        'name_en' => $item->detail->notificationTitleEN,
                    ]);

                    $event->notifySubscribed();
                }
            }

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
