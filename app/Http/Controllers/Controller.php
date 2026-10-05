<?php

namespace App\Http\Controllers;

use App\Enums\EventTypes;
use App\Http\Requests\EventRequest;
use App\Models\Address;
use App\Models\Event;
use App\Models\ServiceCenter;
use App\Models\UserStat;
use App\Support\Locale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index(EventRequest $request): View|RedirectResponse
    {
        // Center pages used to be the home page filtered by ?service_center_id=.
        if ($request->has('service_center_id')) {
            $serviceCenter = ServiceCenter::query()->findOrFail($request->get('service_center_id'));

            return redirect(Locale::route('service-center', ['serviceCenter' => $serviceCenter] + $request->except('service_center_id')), 301);
        }

        return $this->events($request);
    }

    public function serviceCenter(EventRequest $request, ServiceCenter $serviceCenter): View|RedirectResponse
    {
        if ($request->route()->originalParameter('serviceCenter') !== $serviceCenter->getRouteKey()) {
            return redirect(Locale::route('service-center', ['serviceCenter' => $serviceCenter] + $request->query()), 301);
        }

        return $this->events($request, $serviceCenter);
    }

    private function events(EventRequest $request, ?ServiceCenter $serviceCenter = null): View
    {
        $currentEvents = Event::query()
            ->current()
            ->with('serviceCenter.subscriptions')
            ->when($serviceCenter, function($query) use ($serviceCenter) {
                $query->where('service_center_id', $serviceCenter->id);
            })
            ->when($request->has('type'), function($query) use ($request) {
                $query->where('type', $request->get('type'));
            })
            ->get();

        // Live refresh on the page only needs the current events block.
        if ($request->boolean('live')) {
            return view('partial.current_events', compact('currentEvents'));
        }

        $title = __('web.title_all');

        if ($serviceCenter) {
            $title = __('web.title_center', ['center' => $serviceCenter->localizedName(app()->getLocale())]);
        }

        if ($request->has('type')) {
            $title = __('web.title_type', ['icon' => EventTypes::tryFrom($request->get('type'))?->getIcon()]);
        }


        if ($serviceCenter) {
            $graphData = $this->getEventsGraphData($serviceCenter);
            $addresses = Address::query()
                ->with('serviceCenter')
                ->where('service_center_id', $serviceCenter->id)
                ->orderBy('total_events', 'DESC')
                ->limit(100)
                ->get();
        } else {
            $addresses = [];
            $graphData = $this->getSubscribesGraphData();
            $overview = $this->getOverviewGraphData();
        }

        $stat = $this->getStatData();

        return view('index', compact([
            'title',
            'currentEvents',
            'graphData',
            'stat',
            'addresses',
        ]) + ['overview' => $overview ?? null]);
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

    private const KINDS = ['all', 'planned', 'emergency'];

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
        return Cache::remember('eventsGraphKinds_' . $serviceCenter->id . '-' . $address?->id . '_' . app()->getLocale(), 60 * 60,
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
                    foreach (self::KINDS as $kind) {
                        foreach ($types as $type) {
                            $affected[$kind][$type->value][$day->format('d.m.Y')] = [];
                        }
                    }
                }

                $addressDays = array_fill_keys(self::KINDS, []);
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
                                $addressDays[$kind][$date][] = __('web.series.' . $event->type->value);
                            }
                        }
                    }
                }

                $total = max($serviceCenter->total_addresses, 1);
                $variants = [];
                foreach (self::KINDS as $kind) {
                    $variants[$kind] = [
                        'label' => __('web.kinds.' . $kind),
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
                    __('web.days_with_outages') => __('web.days_of', ['days' => $daysWithOutages, 'total' => count($labels)]),
                    __('web.worst_day') => $worst && $pastDays[$worst]
                        ? substr($worst, 0, 5) . ' — ' . min(round($pastDays[$worst] / $total * 100), 100) . '%'
                        : '—',
                    __('web.planned_emergency') => $kindCounts['planned'] . ' / ' . $kindCounts['emergency'],
                    __('web.center_addresses') => $serviceCenter->total_addresses,
                ];
                if ($address) {
                    $summary[__('web.address_outages')] = __('web.days_short', ['count' => count($addressDays['all'])]);
                }

                return [
                    'type' => 'bar',
                    'title' => __('web.events_chart_title'),
                    'labels' => $labels,
                    'datasets' => $variants['all']['datasets'],
                    'variants' => $variants,
                    'summary' => $summary,
                    'yTitle' => __('web.events_chart_y'),
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
                'label' => __('web.series.' . $type->value),
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
                'label' => __('web.address_series', ['address' => $address->localizedName(app()->getLocale())]),
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
        return Cache::remember('overviewGraph_' . app()->getLocale(), 60 * 60, function() {
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
            foreach (self::KINDS as $kind) {
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
            foreach (self::KINDS as $kind) {
                $variants[$kind] = [
                    'label' => __('web.kinds.' . $kind),
                    'datasets' => array_map(fn(EventTypes $type) => [
                        'type' => 'bar',
                        'label' => __('web.series.' . $type->value),
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
                'title' => __('web.overview_title'),
                'labels' => $labels,
                'datasets' => $variants['all']['datasets'],
                'variants' => $variants,
                'summary' => [
                    __('web.last_30_days') => $lastMonth->count()
                        . ($change !== null ? ' (' . ($change > 0 ? '+' : '') . $change . '%)' : ''),
                    __('web.emergency_share') => $known->isNotEmpty()
                        ? __('web.percent_of', ['percent' => round($emergency / $known->count() * 100), 'total' => $known->count()])
                        : '—',
                    __('web.median_duration') => $hours->isNotEmpty()
                        ? __('web.hours_short', ['hours' => round($hours[intdiv($hours->count(), 2)], 1)])
                        : '—',
                    __('web.most_often') => $topCenterName
                        ? $topCenterName->localizedName(app()->getLocale()) . ' — ' . $topCenter->first()
                        : '—',
                ],
                'yTitle' => __('web.overview_y'),
                'todayIndex' => count($labels) - 6,
            ];
        });
    }

    /**
     * Totals from the daily snapshots, so deletions show up as drops instead of
     * rewriting the past. Days without a snapshot are left as gaps.
     */
    private function getSubscribesGraphData(): array
    {
        return Cache::remember('graphData_users_v5_' . app()->getLocale(), 60 * 60,
            function() {
                $fromDate = now()->subDays(30)->startOfDay();

                $stats = UserStat::query()
                    ->where('date', '>=', $fromDate->toDateString())
                    ->get()
                    ->keyBy(fn(UserStat $stat) => $stat->date->format('d.m.Y'));

                $labels = [];
                for ($day = $fromDate->copy(); $day->lte(today()); $day->addDay()) {
                    $labels[] = $day->format('d.m.Y');
                }

                $series = [
                    'users' => [__('web.users'), '#2a78d6'],
                    'subscriptions' => [__('web.subscriptions'), '#eb6834'],
                    'filtered' => [__('web.filtered'), '#1baa7a'],
                ];

                $datasets = [];
                foreach ($series as $column => [$label, $color]) {
                    $datasets[] = [
                        'label' => $label,
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'fill' => false,
                        'data' => array_map(fn($date) => $stats->get($date)?->$column, $labels),
                    ];
                }

                return [
                    'labels' => $labels,
                    'datasets' => $datasets,
                    'title' => __('web.users_title'),
                    'xTitle' => __('web.users_x'),
                    'yTitle' => __('web.users_y'),
                    'partialIndex' => count($labels) - 1,
                ];
            });
    }

    private function getStatData(): array
    {
        return Cache::remember('statData_' . app()->getLocale(), 60 * 60, function() {
            return [
                __('web.stat_service_centers') => '<a href="' . Locale::route('service-centers') . '" class="text-cyan-700 hover:underline">' . ServiceCenter::query()->count() . '</a>',
                __('web.stat_addresses') => '<a href="' . Locale::route('addresses') . '" class="text-cyan-700 hover:underline">' . Address::query()->count() . '</a>',
                __('web.stat_events') => Event::query()->count(),
                __('web.stat_notified_today') => Cache::get('notified_today', 0),
            ];
        });
    }
}
