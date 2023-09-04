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
use Illuminate\View\View;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(EventRequest $request): View
    {
        $currentEvents = Event::query()
            ->current()
            ->when($request->has('service_center_id'), function($query) use ($request) {
                $query->where('service_center_id', $request->get('service_center_id'));
            })
            ->when($request->has('type'), function($query) use ($request) {
                $query->where('type', $request->get('type'));
            })
            ->get();

        $title = 'Отключения воды, электричества и газа в Грузии';

        if ($request->has('service_center_id')) {
            $serviceCenter = ServiceCenter::query()
                ->findOrFail($request->get('service_center_id'));
            $title = $serviceCenter->name_ru . ' - отключения воды, электричества и газа';
        }

        if ($request->has('type')) {
            $title = 'Отключения ' . EventTypes::tryFrom($request->get('type'))?->getIcon() . ' в Грузии';
        }

        if ($request->has('service_center_id')) {
            $graphData = $this->getEventsGraphData($request);
        } else {
            $graphData = $this->getSubscribesGraphData();
        }

        $stat = $this->getStatData();

        return view('index', compact([
            'title',
            'currentEvents',
            'graphData',
            'stat',
        ]));
    }

    public function event(Event $event): View
    {
        return view('event', compact('event'));
    }

    private function randColor(): string
    {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    private function getEventsGraphData(EventRequest $request): array
    {
        return Cache::remember('graphData_id_' . $request->get('service_center_id'), 60 * 60,
            function() use ($request) {
                $dist = 120;
                $fromDate = now()->subDays($dist);

                $events = Event::query()
                    ->with(['serviceCenter', 'addresses'])
                    ->where('start', '>=', $fromDate)
                    ->whereIn('type', [EventTypes::water, EventTypes::energy])
                    ->where('service_center_id', $request->get('service_center_id'))
                    ->orderBy('start')
                    ->get();

                $graphData = [];
                $graphData['labels'] = [];
                $graphData['datasets'] = [];

                while ($fromDate->lessThan(now()->addDays(5))) {
                    $graphData['labels'][] = $fromDate->format('d.m.Y');
                    $fromDate->addDay();
                }

                $serviceCenters = ServiceCenter::query()
                    ->where('id', $request->get('service_center_id'))
                    ->orderBy('total_events', 'DESC')
                    ->get();

                foreach ($serviceCenters as $serviceCenter) {
                    $color = $this->randColor();
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
                                $number = ($serviceCenter->total_addresses - $event->total_addresses) / $serviceCenter->total_addresses * 100;
                                $graphData['datasets'][$serviceCenter->id]['data'][] = $number;
                                break;
                            }
                        }
                        if (!$found) {
                            $graphData['datasets'][$serviceCenter->id]['data'][] = 100;
                        }
                    }
                }

                $graphData['datasets'] = array_values($graphData['datasets']);

                $graphData['title'] = 'Статистика отключений (только вода и электроэнергия)';
                $graphData['xTitle'] = 'Даты';
                $graphData['yTitle'] = '% адресов за вычетом отключенных';

                return $graphData;
            });
    }

    private function getSubscribesGraphData(): array
    {
        return Cache::remember('graphData_users', 0,
            function() {
                $dist = 30;
                $fromDate = now()->subDays($dist);

                $users = BotUser::query()
                    ->where('created_at', '>=', $fromDate)
                    ->whereNot('is_bot')->get();

                $subscriptions = Subscriptions::query()
                    ->where('created_at', '>=', $fromDate)
                    ->get();

                $totalUsers = BotUser::query()
                    ->where('created_at', '<', $fromDate)
                    ->whereNot('is_bot')->count();

                $totalSubscriptions = Subscriptions::query()
                    ->where('created_at', '<', $fromDate)
                    ->count();

                $graphData = [];
                $graphData['labels'] = [];
                $graphData['datasets'] = [];

                while ($fromDate->lessThan(now())) {
                    $graphData['labels'][] = $fromDate->format('d.m.Y');
                    $fromDate->addDay();
                }

                $color = $this->randColor();
                $graphData['datasets'][1]['label'] = 'Пользователи';
                $graphData['datasets'][1]['backgroundColor'] = $color;
                $graphData['datasets'][1]['borderColor'] = $color;
                $graphData['datasets'][1]['fill'] = false;

                $color = $this->randColor();
                $graphData['datasets'][2]['label'] = 'Подписки';
                $graphData['datasets'][2]['backgroundColor'] = $color;
                $graphData['datasets'][2]['borderColor'] = $color;
                $graphData['datasets'][2]['fill'] = false;

                foreach ($graphData['labels'] as $date) {
                    foreach ($users as $user) {
                        if ($date === $user->created_at->format('d.m.Y')) {
                            $totalUsers++;
                        }
                    }
                    $graphData['datasets'][1]['data'][] = $totalUsers;

                    foreach ($subscriptions as $subscription) {
                        if ($date === $subscription->created_at->format('d.m.Y')) {
                            $totalSubscriptions++;
                        }
                    }
                    $graphData['datasets'][2]['data'][] = $totalSubscriptions;
                }

                $graphData['datasets'] = array_values($graphData['datasets']);

                $graphData['title'] = 'Число пользователей бота';
                $graphData['xTitle'] = 'Даты';
                $graphData['yTitle'] = 'Пользователи';

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
                'Разослано сегодня' => Cache::get('notified_today', 0),
            ];
        });
    }
}
