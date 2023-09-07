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
            ->with('serviceCenter.subscriptions')
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
            $serviceCenter = ServiceCenter::query()->find($request->get('service_center_id'));
            $graphData = $serviceCenter ? $this->getEventsGraphData($serviceCenter) : [];
            $addresses = Address::query()
                ->with('serviceCenter')
                ->where('service_center_id', $request->get('service_center_id'))
                ->orderBy('total_events', 'DESC')
                ->limit(100)
                ->get();
        } else {
            $addresses = [];
            $graphData = $this->getSubscribesGraphData();
        }

        $stat = $this->getStatData();

        return view('index', compact([
            'title',
            'currentEvents',
            'graphData',
            'stat',
            'addresses',
        ]));
    }

    public function serviceCenters(): View
    {
        $serviceCenters = ServiceCenter::query()
            ->withCount('subscriptions')
            ->orderBy('total_events', 'DESC')
            ->orderBy('subscriptions_count', 'DESC')
            ->get();

        $stat = $this->getStatData();

        return view('service-centers', compact('serviceCenters', 'stat'));
    }

    public function addresses(): View
    {
        $addresses = Address::query()
            ->with('serviceCenter')
            ->orderBy('total_events', 'DESC')
            ->limit(200)
            ->get();

        $stat = $this->getStatData();

        return view('addresses', compact('addresses', 'stat'));
    }


    public function address(Address $address): View
    {
        $stat = $this->getStatData();
        $address->load('serviceCenter', 'events');
        $graphData = $this->getEventsGraphData($address->serviceCenter, $address);

        return view('address', compact('address', 'stat', 'graphData'));
    }

    public function event(Event $event): View
    {
        $stat = $this->getStatData();

        return view('event', compact('event', 'stat'));
    }

    /**
     * @throws \Exception
     */
    private function randColor(): string
    {
        return '#' . str_pad(dechex(random_int(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }

    private function hexInvert(string $color): string
    {
        $color = trim($color);
        $prependHash = false;
        if (str_contains($color, '#')) {
            $prependHash = true;
            $color = str_replace('#', '', $color);
        }
        $len = strlen($color);
        if ($len === 3 || $len === 6) {
            if ($len === 3) {
                $color = preg_replace('/(.)(.)(.)/', "\\1\\1\\2\\2\\3\\3", $color);
            }
        } else {
            throw new \RuntimeException("Недопустимая длина HEX кода ($len). Длина должна быть 3 или 6 символов.");
        }
        if (!preg_match('/^[a-f0-9]{6}$/i', $color)) {
            throw new \RuntimeException(sprintf('Неверная hex строка #%s', htmlspecialchars($color, ENT_QUOTES)));
        }

        $r = dechex(255 - hexdec(substr($color, 0, 2)));
        $r = (strlen($r) > 1) ? $r : '0' . $r;
        $g = dechex(255 - hexdec(substr($color, 2, 2)));
        $g = (strlen($g) > 1) ? $g : '0' . $g;
        $b = dechex(255 - hexdec(substr($color, 4, 2)));
        $b = (strlen($b) > 1) ? $b : '0' . $b;

        return ($prependHash ? '#' : '') . $r . $g . $b;
    }

    private function getEventsGraphData(ServiceCenter $serviceCenter, Address $address = null): array
    {
        return Cache::remember('graphData_id_' . $serviceCenter->id . '-' . $address?->id, 60 * 60,
            function() use ($serviceCenter, $address) {
                $dist = 120;
                $fromDate = now()->subDays($dist);

                $events = Event::query()
                    ->with(['serviceCenter', 'addresses'])
                    ->where('start', '>=', $fromDate)
                    ->whereIn('type', [EventTypes::water, EventTypes::energy])
                    ->where('service_center_id', $serviceCenter->id)
                    ->orderBy('start')
                    ->get();

                $graphData = [];
                $graphData['labels'] = [];
                $graphData['datasets'] = [];

                while ($fromDate->lessThan(now()->addDays(5))) {
                    $graphData['labels'][] = $fromDate->format('d.m.Y');
                    $fromDate->addDay();
                }

                $color = $this->randColor();
                $graphData['datasets'][$serviceCenter->id]['label'] = $serviceCenter->name_ru;
                $graphData['datasets'][$serviceCenter->id]['backgroundColor'] = $color;
                $graphData['datasets'][$serviceCenter->id]['borderColor'] = $color;
                $graphData['datasets'][$serviceCenter->id]['fill'] = false;

                if ($address) {
                    $color = $this->hexInvert($color);
                    $graphData['datasets']['addr_' . $address->id]['label'] = $address->translit;
                    $graphData['datasets']['addr_' . $address->id]['backgroundColor'] = $color;
                    $graphData['datasets']['addr_' . $address->id]['borderColor'] = $color;
                    $graphData['datasets']['addr_' . $address->id]['fill'] = false;
                }

                foreach ($graphData['labels'] as $date) {
                    $found = false;
                    foreach ($events as $event) {
                        if ($date === $event->start->format('d.m.Y')) {
                            if ($serviceCenter->id === $event->serviceCenter->id) {
                                $found = $event;
                                $number = round(($serviceCenter->total_addresses - $event->total_addresses) / $serviceCenter->total_addresses * 100, 2);
                                $graphData['datasets'][$serviceCenter->id]['data'][] = $number;
                            }
                        }
                    }

                    if (!$found) {
                        $graphData['datasets'][$serviceCenter->id]['data'][] = 100;
                    } else {
                        $events = $events->filter(function($event) use ($found) {
                            return $event->id = $found->id;
                        });
                    }

                    if ($address) {
                        $found = false;
                        foreach ($address->events()->where('start', '>=', $fromDate) as $event) {
                            if ($address && $date === $event->start->format('d.m.Y') && $address->service_center_id === $event->service_center_id) {
                                $found = $event;
                                $graphData['datasets']['addr_' . $address->id]['data'][] = 0;
                            }
                        }
                        if (!$found) {
                            $graphData['datasets']['addr_' . $address->id]['data'][] = 100;
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

                $color = $this->hexInvert($color);
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
                'Сервисных центров' => '<a href="' . route('service-centers') . '" class="text-cyan-600">' . ServiceCenter::query()->count() . '</a>',
                'Адресов в базе' => '<a href="' . route('addresses') . '" class="text-cyan-600">' . Address::query()->count() . '</a>',
                'Событий всего' => Event::query()->count(),
                'Разослано сегодня' => Cache::get('notified_today', 0),
            ];
        });
    }
}
