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
            $overview = $this->getOverviewGraphData();
            $streetFilter = $this->getStreetFilterGraphData();
        }

        $stat = $this->getStatData();

        return view('index', compact([
            'title',
            'currentEvents',
            'graphData',
            'stat',
            'addresses',
        ]) + ['overview' => $overview ?? null, 'streetFilter' => $streetFilter ?? null]);
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

    private const SERIES_COLORS = [
        'water' => '#2a78d6',
        'energy' => '#eb6834',
        'gas' => '#1baf7a',
    ];

    private const SERIES_LABELS = [
        'water' => 'Вода',
        'energy' => 'Электричество',
        'gas' => 'Газ',
    ];

    private const KIND_LABELS = [
        'all' => 'Все',
        'planned' => 'Плановые',
        'emergency' => 'Аварийные',
    ];

    /**
     * Share of the service center's addresses cut off on each day, split by
     * outage type. An event counts on every day between its start and finish,
     * and an address hit by several events of one type on a day counts once.
     * Planned and emergency outages get their own variant of the datasets;
     * events of unknown kind (loaded before the sources were told apart) show
     * up only among all of them.
     */
    private function getEventsGraphData(ServiceCenter $serviceCenter, Address $address = null): array
    {
        return Cache::remember('eventsGraphKinds_' . $serviceCenter->id . '-' . $address?->id, 60 * 60,
            function() use ($serviceCenter, $address) {
                $types = [EventTypes::water, EventTypes::energy];
                $firstDay = now()->subMonths(6)->startOfDay();
                $lastDay = now()->addDays(5)->startOfDay();

                $events = Event::query()
                    ->with('addresses:id')
                    ->where('finish', '>=', $firstDay)
                    ->where('start', '<', $lastDay->copy()->addDay())
                    ->whereIn('type', $types)
                    ->where('service_center_id', $serviceCenter->id)
                    ->get();

                $labels = [];
                $affected = [];
                for ($day = $firstDay->copy(); $day->lte($lastDay); $day->addDay()) {
                    $labels[] = $day->format('d.m.Y');
                    foreach (array_keys(self::KIND_LABELS) as $kind) {
                        foreach ($types as $type) {
                            $affected[$kind][$type->value][$day->format('d.m.Y')] = [];
                        }
                    }
                }

                $addressDays = array_fill_keys(array_keys(self::KIND_LABELS), []);
                $kindCounts = ['planned' => 0, 'emergency' => 0];
                foreach ($events as $event) {
                    $kinds = ['all'];
                    if ($event->planned !== null) {
                        $kinds[] = $event->planned ? 'planned' : 'emergency';
                        $kindCounts[$kinds[1]]++;
                    }
                    $day = $event->start->copy()->startOfDay()->max($firstDay)->copy();
                    $until = $event->finish->copy()->startOfDay()->min($lastDay)->copy();
                    $ids = $event->addresses->pluck('id')->all();
                    $hitsAddress = $address && in_array($address->id, $ids, true);
                    for (; $day->lte($until); $day->addDay()) {
                        $date = $day->format('d.m.Y');
                        foreach ($kinds as $kind) {
                            $affected[$kind][$event->type->value][$date] += array_fill_keys($ids, true);
                            if ($hitsAddress) {
                                $addressDays[$kind][$date][] = self::SERIES_LABELS[$event->type->value];
                            }
                        }
                    }
                }

                $total = max($serviceCenter->total_addresses, 1);
                $variants = [];
                foreach (self::KIND_LABELS as $kind => $kindLabel) {
                    $variants[$kind] = [
                        'label' => $kindLabel,
                        'datasets' => $this->buildOutageDatasets($types, $labels, $affected[$kind], $total, $address, $addressDays[$kind]),
                    ];
                }

                $dayTotals = array_fill_keys($labels, 0);
                foreach ($types as $type) {
                    foreach ($affected['all'][$type->value] as $date => $ids) {
                        $dayTotals[$date] += count($ids);
                    }
                }

                $daysWithOutages = count(array_filter($dayTotals));
                $pastDays = array_slice($dayTotals, 0, count($labels) - 5, true);
                $worst = $pastDays ? array_keys($pastDays, max($pastDays))[0] : null;

                $summary = [
                    'Дней с отключениями' => $daysWithOutages . ' из ' . count($labels),
                    'Худший день' => $worst && $pastDays[$worst]
                        ? substr($worst, 0, 5) . ' — ' . min(round($pastDays[$worst] / $total * 100), 100) . '%'
                        : '—',
                    'Плановых / аварийных' => $kindCounts['planned'] . ' / ' . $kindCounts['emergency'],
                    'Адресов в центре' => $serviceCenter->total_addresses,
                ];
                if ($address) {
                    $summary['Отключений по адресу'] = count($addressDays['all']) . ' дн.';
                }

                return [
                    'type' => 'bar',
                    'title' => 'Статистика отключений за полгода (вода и электричество)',
                    'labels' => $labels,
                    'datasets' => $variants['all']['datasets'],
                    'variants' => $variants,
                    'summary' => $summary,
                    'yTitle' => '% адресов без услуги',
                    'yUnit' => '%',
                    'todayIndex' => count($labels) - 6,
                ];
            });
    }

    /**
     * @param EventTypes[] $types
     * @param array<string, array<string, array<int, true>>> $affected type => date => set of address ids
     * @param array<string, string[]> $addressDays date => types that cut the address off
     */
    private function buildOutageDatasets(array $types, array $labels, array $affected, int $total, ?Address $address, array $addressDays): array
    {
        $datasets = [];
        foreach ($types as $type) {
            $counts = array_map('count', array_values($affected[$type->value]));
            $datasets[] = [
                'type' => 'bar',
                'label' => self::SERIES_LABELS[$type->value],
                'backgroundColor' => self::SERIES_COLORS[$type->value],
                'data' => array_map(fn($count) => min(round($count / $total * 100, 1), 100), $counts),
                'counts' => $counts,
                'stack' => 'outages',
                'order' => 2,
            ];
        }

        if ($address) {
            $datasets[] = [
                'type' => 'line',
                'showLine' => false,
                'label' => 'Отключение по адресу ' . $address->translit,
                'backgroundColor' => '#1e293b',
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
                'pointStyle' => 'triangle',
                'pointRadius' => 7,
                'pointHoverRadius' => 9,
                // Sits on top of that day's bar.
                'data' => array_map(
                    fn($i, $date) => isset($addressDays[$date])
                        ? array_sum(array_map(fn($dataset) => $dataset['data'][$i], $datasets))
                        : null,
                    array_keys($labels),
                    $labels,
                ),
                'kinds' => array_map(fn($date) => implode(', ', array_unique($addressDays[$date] ?? [])), $labels),
                'order' => 1,
            ];
        }

        return $datasets;
    }

    /**
     * Country-wide overview for the home page: outages started on each day by
     * type, with the same All / Planned / Emergency variants as a center's
     * chart, and the last 30 days summed up against the 30 before.
     */
    private function getOverviewGraphData(): array
    {
        return Cache::remember('overviewGraph', 60 * 60, function() {
            $types = [EventTypes::water, EventTypes::energy, EventTypes::gas];
            $firstDay = now()->subDays(90)->startOfDay();
            $lastDay = now()->addDays(5)->startOfDay();
            $monthAgo = now()->subDays(30);
            $twoMonthsAgo = now()->subDays(60);

            $events = Event::query()
                ->where('start', '>=', $firstDay)
                ->where('start', '<', $lastDay->copy()->addDay())
                ->get();

            $labels = [];
            for ($day = $firstDay->copy(); $day->lte($lastDay); $day->addDay()) {
                $labels[] = $day->format('d.m.Y');
            }
            $empty = array_fill_keys($labels, 0);
            $counts = [];
            foreach (array_keys(self::KIND_LABELS) as $kind) {
                foreach ($types as $type) {
                    $counts[$kind][$type->value] = $empty;
                }
            }

            $lastMonth = $events->filter(fn(Event $event) => $event->start->between($monthAgo, now()));
            foreach ($events as $event) {
                $date = $event->start->format('d.m.Y');
                $counts['all'][$event->type->value][$date]++;
                if ($event->planned !== null) {
                    $counts[$event->planned ? 'planned' : 'emergency'][$event->type->value][$date]++;
                }
            }

            $variants = [];
            foreach (self::KIND_LABELS as $kind => $kindLabel) {
                $variants[$kind] = [
                    'label' => $kindLabel,
                    'datasets' => array_map(fn(EventTypes $type) => [
                        'type' => 'bar',
                        'label' => self::SERIES_LABELS[$type->value],
                        'backgroundColor' => self::SERIES_COLORS[$type->value],
                        'data' => array_values($counts[$kind][$type->value]),
                        'stack' => 'outages',
                    ], $types),
                ];
            }

            $previous = $events->filter(fn(Event $event) => $event->start->between($twoMonthsAgo, $monthAgo))->count();
            $change = $previous ? round(($lastMonth->count() - $previous) / $previous * 100) : null;

            $known = $lastMonth->whereNotNull('planned');
            $emergency = $known->where('planned', false)->count();

            $hours = $lastMonth
                ->map(fn(Event $event) => $event->start->diffInMinutes($event->finish) / 60)
                ->sort()
                ->values();

            $topCenter = $lastMonth->countBy('service_center_id')->sortDesc();
            $topCenterName = $topCenter->isNotEmpty()
                ? ServiceCenter::query()->find($topCenter->keys()->first())
                : null;

            return [
                'id' => 'overviewChart',
                'type' => 'bar',
                'title' => 'Отключения по Грузии за 90 дней',
                'labels' => $labels,
                'datasets' => $variants['all']['datasets'],
                'variants' => $variants,
                'summary' => [
                    'За 30 дней' => $lastMonth->count()
                        . ($change !== null ? ' (' . ($change > 0 ? '+' : '') . $change . '%)' : ''),
                    'Аварийных' => $known->isNotEmpty()
                        ? round($emergency / $known->count() * 100) . '% из ' . $known->count()
                        : '—',
                    'Длятся (медиана)' => $hours->isNotEmpty()
                        ? round($hours[intdiv($hours->count(), 2)], 1) . ' ч'
                        : '—',
                    'Чаще всего' => $topCenterName
                        ? ($topCenterName->name_ru ?: $topCenterName->name) . ' — ' . $topCenter->first()
                        : '—',
                ],
                'yTitle' => 'Отключений в день',
                'todayIndex' => count($labels) - 6,
            ];
        });
    }

    private function getSubscribesGraphData(): array
    {
        return Cache::remember('graphData_users', 60 * 60,
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

                $color = '#2a78d6';
                $graphData['datasets'][1]['label'] = 'Пользователи';
                $graphData['datasets'][1]['backgroundColor'] = $color;
                $graphData['datasets'][1]['borderColor'] = $color;
                $graphData['datasets'][1]['fill'] = false;

                $color = '#eb6834';
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

    /**
     * There is no filter history: a filter counts from its last edit (only
     * /filter touches updated_at), and cleared filters drop out entirely.
     */
    private function getStreetFilterGraphData(): array
    {
        return Cache::remember('graphData_street_filter', 60 * 60, function() {
            $fromDate = now()->subDays(30)->startOfDay();

            $days = Subscriptions::query()
                ->whereNotNull('street_filter')
                ->where('updated_at', '>=', $fromDate)
                ->get(['updated_at'])
                ->countBy(fn(Subscriptions $subscription) => $subscription->updated_at->format('d.m.Y'));

            $total = Subscriptions::query()
                ->whereNotNull('street_filter')
                ->where('updated_at', '<', $fromDate)
                ->count();

            $labels = [];
            $data = [];
            for ($date = $fromDate->copy(); $date->lessThan(now()); $date->addDay()) {
                $label = $date->format('d.m.Y');
                $total += $days[$label] ?? 0;
                $labels[] = $label;
                $data[] = $total;
            }

            $color = '#1baa7a';

            return [
                'id' => 'streetFilterChart',
                'title' => 'Подписки с фильтром по улицам',
                'yTitle' => 'Подписки',
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'С фильтром по улицам',
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'fill' => false,
                    'data' => $data,
                ]],
            ];
        });
    }

    private function getStatData(): array
    {
        return Cache::remember('statData', 60 * 60, function() {
            return [
                'Сервисных центров' => '<a href="' . route('service-centers') . '" class="text-cyan-700 hover:underline">' . ServiceCenter::query()->count() . '</a>',
                'Адресов в базе' => '<a href="' . route('addresses') . '" class="text-cyan-700 hover:underline">' . Address::query()->count() . '</a>',
                'Событий всего' => Event::query()->count(),
                'Разослано сегодня' => Cache::get('notified_today', 0),
            ];
        });
    }
}
