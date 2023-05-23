<?php

namespace App\Http\Controllers;

use App\Enums\EventTypes;
use App\Http\Requests\EventRequest;
use App\Models\Address;
use App\Models\BotUser;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Models\Subscriptions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(EventRequest $request)
    {
        $currentEvents = Event::query()
            ->current()
            ->when($request->has('service_center_id'), function($query) use ($request) {
                $query->where('service_center_id', $request->get('service_center_id'));
            })
            ->get();

        $graphData = $this->getGraphData($request);

        $stat = $this->getStatData();

        return view('welcome', compact([
            'currentEvents',
            'graphData',
            'stat',
        ]));
    }

    function rand_color()
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    private function getGraphData(EventRequest $request): array
    {
        return Cache::remember('graphData_id_' . $request->get('service_center_id'), 60 * 60, function() use ($request) {
            $events = Event::query()
                ->with(['serviceCenter', 'addresses'])
                ->where('start', '>=', now()->subDays(120))
                ->whereIn('type', [EventTypes::water, EventTypes::energy])
                ->when($request->has('service_center_id'), function($query) use ($request) {
                    $query->where('service_center_id', $request->get('service_center_id'));
                })
                ->orderBy('start')
                ->get();

            $graphData = [];
            $graphData['labels'] = [];
            $graphData['datasets'] = [];

            foreach ($events as $event) {
                $date = $event->start->format('d.m.Y');
                if (!in_array($date, $graphData['labels'], true)) {
                    $graphData['labels'][] = $date;
                }
            }

            $serviceCenters = ServiceCenter::query()
                ->when($request->has('service_center_id'), function($query) use ($request) {
                    $query->where('id', $request->get('service_center_id'));
                })
                ->orderBy('total_events', 'DESC')
                ->limit(10)
                ->get();

            foreach ($serviceCenters as $serviceCenter) {
                $color = $this->rand_color();
                $graphData['datasets'][$serviceCenter->id]['label'] = $serviceCenter->name_ru;
                $graphData['datasets'][$serviceCenter->id]['backgroundColor'] = $color;
                $graphData['datasets'][$serviceCenter->id]['borderColor'] = $color;
                $graphData['datasets'][$serviceCenter->id]['fill'] = false;
            }

            foreach ($graphData['labels'] as $date) {
                foreach ($serviceCenters as $serviceCenter) {
                    $found = false;
                    foreach ($events as $event) {
                        if ($date === $event->start->format('d.m.Y') && $serviceCenter->id === $event->serviceCenter->id) {
                            $found = true;
                            $number = $serviceCenter->total_addresses - $event->total_addresses;
                            $graphData['datasets'][$serviceCenter->id]['data'][] = $number;
                            break;
                        }
                    }
                    if (!$found) {
                        $graphData['datasets'][$serviceCenter->id]['data'][] = $serviceCenter->total_addresses;
                    }
                }
            }

            $graphData['datasets'] = array_values($graphData['datasets']);

            return $graphData;
        });
    }

    private function getStatData(): array
    {
        return Cache::remember('statData', 60 * 60, function() {
            return [
                'Сервисных центров' => ServiceCenter::query()->count(),
                'Адресов в базе' => Address::query()->count(),
                'Событий в базе' => Event::query()->count(),
                'Подписчиков' => BotUser::query()->count(),
                'Подписок' => Subscriptions::query()->count(),
            ];
        });
    }
}
