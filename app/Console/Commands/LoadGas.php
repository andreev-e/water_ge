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
    protected $signature = 'load-gas {--from= : Import the archive since this date, without notifying anyone}';

    protected $description = 'Command description';

    /**
     * Normally reads only the first page: the newest outages. With --from it
     * walks the archive, newest first, until a whole page ends before that date.
     */
    public function handle(Client $client)
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;

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
            $created = $this->store($data->items, !$from);

            if (!$from) {
                SourceStatus::markUpdated(SourceStatus::GAS);
                return;
            }

            $this->line('page ' . $page . ': ' . $created . ' new');
            $recent = collect($data->items)->contains(fn($item) => Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->end)->gte($from));
            $page++;
            usleep(200_000);
        } while ($recent && $data->hasNext);
    }

    /**
     * @return int how many new events were created
     */
    private function store(array $items, bool $notify): int
    {
        $serviceCenters = ServiceCenter::all();
        $serviceCenterNames = $serviceCenters->mapWithKeys(function (ServiceCenter $serviceCenter) {
            return [$serviceCenter->id => str_replace(
                array('ს სერვის ცენტრი', 'ს სერვის ცენთრი', 'აბაშა', 'ყვარელი', ' სერვის ცენთრი'),
                array('', '', 'აბაში', 'ყვარლი', ''),
                $serviceCenter->name
            )];
        });

        $starts = collect($items)
            ->map(fn($item) => Carbon::createFromFormat('Y-m-d\TH:i:sO', $item->start));

        $existingEvents = Event::query()
            ->where('type', EventTypes::gas)
            ->whereIn('start', $starts)
            ->get()
            ->keyBy(fn(Event $event) => $event->service_center_id . '|' . $event->start->toDateTimeString() . '|' . $event->finish->toDateTimeString());

        $created = 0;
        foreach ($items as $item) {
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
            $planned = $item->type === 'Planned';

            if ($existingEvents->has($key)) {
                $event = $existingEvents->get($key);
                if ($event->planned === null) {
                    $event->update(['planned' => $planned]);
                }
                continue;
            }

            /* @var $event Event */
            $event = Event::query()->create([
                'service_center_id' => $foundedServiceCenter,
                'start' => $start,
                'finish' => $finish,
                'total_addresses' => 0,
                'type' => EventTypes::gas,
                'name' => $item->detail->notificationTitle,
                'name_en' => $item->detail->notificationTitleEN,
                'planned' => $planned,
            ]);
            $existingEvents->put($key, $event);
            $created++;

            // Archive imports skip the paid translation too: readers fall back to name_en.
            if ($notify) {
                $event->translateName();
                $event->notifySubscribed();
                $event->publishToFacebook();
            }
        }

        return $created;
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
