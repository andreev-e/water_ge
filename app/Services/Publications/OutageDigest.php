<?php

namespace App\Services\Publications;

use App\Enums\EventTypes;
use App\Models\Address;
use App\Models\Event;
use App\Models\Subscriptions;
use App\Models\UserStat;
use App\Support\Locale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Цифры сводки: что случилось за неделю или месяц и как это выглядит рядом
 * с прошлым периодом. Считается только по своей базе — events, address_event
 * и user_stats, — поэтому сводку можно пересобрать в любой момент и получить те же числа.
 */
class OutageDigest
{
    /** Меньше этого числа отключений — выводов о периоде делать нельзя. */
    public const MIN_EVENTS = 3;

    public const TOP_CENTERS = 5;

    public const TOP_ADDRESSES = 5;

    /**
     * @return array{
     *     period: array{type: string, start: string, end: string, label: string},
     *     previous: array{type: string, start: string, end: string, label: string},
     *     reliable: bool,
     *     outages: array<string, mixed>,
     *     audience: array<string, mixed>
     * }
     */
    public function forPeriod(string $type, CarbonImmutable $date): array
    {
        $start = DigestPeriod::start($type, $date);
        $end = DigestPeriod::end($type, $start);
        $previousStart = DigestPeriod::previousStart($type, $start);
        $previousEnd = DigestPeriod::end($type, $previousStart);

        $current = $this->metrics($start, $end);
        $previous = $this->metrics($previousStart, $previousEnd);

        return [
            'period' => DigestPeriod::data($type, $start, $end),
            'previous' => DigestPeriod::data($type, $previousStart, $previousEnd),
            'reliable' => $current['total'] >= self::MIN_EVENTS,
            'outages' => $current + [
                'change_percent' => self::change($current['total'], $previous['total']),
                'addresses_change_percent' => self::change($current['addresses'], $previous['addresses']),
                'duration_change_percent' => self::change($current['median_duration_hours'], $previous['median_duration_hours']),
            ],
            'audience' => $this->audience($start, $end),
        ];
    }

    /**
     * Цифры одного периода: отключения по видам, география, длительность и охват адресов.
     *
     * @return array<string, mixed>
     */
    private function metrics(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->startOfDay();
        $to = $end->addDay()->startOfDay();   // граница «до» не включается
        $events = fn () => Event::query()->where('start', '>=', $from)->where('start', '<', $to);

        $total = $events()->count();

        $byType = array_fill_keys(array_map(fn (EventTypes $type) => $type->value, EventTypes::cases()), 0);
        foreach ($events()->selectRaw('type, COUNT(*) AS events')->groupBy('type')->pluck('events', 'type') as $type => $count) {
            if (array_key_exists($type, $byType)) {
                $byType[$type] = (int) $count;
            }
        }

        $planned = $events()->where('planned', true)->count();
        $emergency = $events()->where('planned', false)->count();
        $unknownKind = max($total - $planned - $emergency, 0);
        $knownKind = $planned + $emergency;

        // Длительности и самое долгое отключение считаются по одним и тем же строкам;
        // у ещё не завершившихся отключений finish пуст, поэтому они в расчёт не идут.
        $timed = $events()
            ->whereNotNull('finish')
            ->with('serviceCenter')
            ->get(['id', 'service_center_id', 'type', 'start', 'finish']);

        $durations = $timed->map(fn (Event $event) => self::durationHours($event))->sort()->values();
        $longest = $timed->sortByDesc(fn (Event $event) => self::durationHours($event))->first();

        $busiestDay = $events()
            ->get(['id', 'start'])
            ->countBy(fn (Event $event) => $event->start->toDateString())
            ->sortDesc();

        $links = $this->addressLinks($from, $to);
        $addresses = (clone $links)->distinct()->count('address_event.address_id');
        $addressLinks = (clone $links)->count();

        $topCenters = DB::table('events')
            ->join('service_centers', 'service_centers.id', '=', 'events.service_center_id')
            ->where('events.start', '>=', $from)
            ->where('events.start', '<', $to)
            ->groupBy('service_centers.id', 'service_centers.name', 'service_centers.name_en', 'service_centers.name_ru')
            ->selectRaw('service_centers.id AS id, service_centers.name AS name, service_centers.name_en AS name_en, service_centers.name_ru AS name_ru, COUNT(*) AS events')
            ->orderByDesc('events')
            ->limit(self::TOP_CENTERS)
            ->get();

        $centerAddresses = (clone $links)
            ->groupBy('events.service_center_id')
            ->selectRaw('events.service_center_id AS service_center_id, COUNT(DISTINCT address_event.address_id) AS addresses')
            ->pluck('addresses', 'service_center_id');

        $topAddresses = (clone $links)
            ->join('addresses', 'addresses.id', '=', 'address_event.address_id')
            ->groupBy('addresses.id', 'addresses.name')
            ->selectRaw('addresses.id AS id, addresses.name AS name, COUNT(*) AS events')
            ->orderByDesc('events')
            ->limit(self::TOP_ADDRESSES)
            ->get();

        return [
            'total' => $total,
            'by_type' => $byType,
            'planned' => $planned,
            'emergency' => $emergency,
            'unknown_kind' => $unknownKind,
            'emergency_share' => $knownKind > 0 ? round($emergency / $knownKind, 3) : null,
            'median_duration_hours' => self::median($durations->all()),
            'longest' => $longest ? [
                'hours' => self::durationHours($longest),
                'type' => $longest->type->value,
                'center' => $this->centerNames($longest->serviceCenter),
            ] : null,
            'busiest_day' => $busiestDay->isEmpty() ? null : [
                'date' => (string) $busiestDay->keys()->first(),
                'events' => (int) $busiestDay->first(),
            ],
            'addresses' => $addresses,
            'address_links' => $addressLinks,
            'customers' => (int) $events()->sum('effected_customers'),
            'top_centers' => $topCenters->map(fn (object $row) => [
                'id' => (int) $row->id,
                'names' => $this->centerNames($row),
                'events' => (int) $row->events,
                'addresses' => (int) ($centerAddresses[$row->id] ?? 0),
            ])->all(),
            'top_addresses' => $topAddresses->map(fn (object $row) => [
                'id' => (int) $row->id,
                'names' => $this->addressNames((string) $row->name),
                'events' => (int) $row->events,
            ])->all(),
        ];
    }

    /** Отключения периода вместе со связью «адрес — событие». */
    private function addressLinks(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('address_event')
            ->join('events', 'events.id', '=', 'address_event.event_id')
            ->where('events.start', '>=', $from)
            ->where('events.start', '<', $to);
    }

    /**
     * Аудитория бота на конец периода против последнего снимка перед его началом.
     *
     * @return array<string, mixed>
     */
    private function audience(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $before = UserStat::query()
            ->where('date', '<', $start->toDateString())
            ->orderByDesc('date')
            ->first();

        $after = UserStat::query()
            ->where('date', '<=', $end->toDateString())
            ->orderByDesc('date')
            ->first();

        return [
            'users_start' => $before?->users,
            'users_end' => $after?->users,
            'users_change' => self::difference($before?->users, $after?->users),
            'subscriptions_start' => $before?->subscriptions,
            'subscriptions_end' => $after?->subscriptions,
            'subscriptions_change' => self::difference($before?->subscriptions, $after?->subscriptions),
            'filtered_end' => $after?->filtered,
            'new_subscriptions' => Subscriptions::query()
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end->addDay())
                ->count(),
        ];
    }

    /**
     * Названия сервисного центра на всех языках сайта: в базе они лежат
     * тремя колонками, а пустые переводы заменяются грузинским оригиналом.
     *
     * @return array<string, string>
     */
    private function centerNames(?object $center): array
    {
        $name = (string) ($center->name ?? '');
        $nameEn = (string) ($center->name_en ?? '');
        $nameRu = (string) ($center->name_ru ?? '');

        return [
            Locale::GEORGIAN => $name !== '' ? $name : ($nameEn !== '' ? $nameEn : $nameRu),
            Locale::ENGLISH => $nameEn !== '' ? $nameEn : ($name !== '' ? $name : $nameRu),
            Locale::WEB_DEFAULT => $nameRu !== '' ? $nameRu : ($name !== '' ? $name : $nameEn),
        ];
    }

    /**
     * Адрес хранится по-грузински; на других языках показываем транслит.
     *
     * @return array<string, string>
     */
    private function addressNames(string $name): array
    {
        $translit = Address::transliterate($name);

        return [
            Locale::GEORGIAN => $name,
            Locale::ENGLISH => $translit,
            Locale::WEB_DEFAULT => $translit,
        ];
    }

    private static function durationHours(Event $event): float
    {
        return round($event->start->diffInMinutes($event->finish) / 60, 1);
    }

    /**
     * Медиана часов: половина отключений короче, половина дольше.
     *
     * @param  array<int, int|float>  $values
     */
    private static function median(array $values): ?float
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }

        sort($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? round((float) $values[$middle], 1)
            : round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }

    /** Изменение в процентах; сравнить не с чем (прошлый период пуст) — null. */
    private static function change(int|float|null $current, int|float|null $previous): ?float
    {
        if ($previous === null || (float) $previous === 0.0) {
            return null;
        }

        return round(((float) ($current ?? 0) - (float) $previous) / (float) $previous * 100, 1);
    }

    private static function difference(?int $before, ?int $after): ?int
    {
        return $before === null || $after === null ? null : $after - $before;
    }
}

