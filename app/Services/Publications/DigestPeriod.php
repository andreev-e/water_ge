<?php

namespace App\Services\Publications;

use App\Models\Publication;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Границы периода сводки и его адрес: неделя начинается в понедельник,
 * месяц — первого числа. Слаг один на все языки, поэтому по нему
 * открывается страница на любом языке сайта.
 */
class DigestPeriod
{
    public static function start(string $type, CarbonImmutable $date): CarbonImmutable
    {
        return $type === Publication::TYPE_MONTH
            ? $date->startOfMonth()
            : $date->startOfWeek(CarbonInterface::MONDAY);
    }

    public static function end(string $type, CarbonImmutable $start): CarbonImmutable
    {
        return $type === Publication::TYPE_MONTH
            ? $start->endOfMonth()->startOfDay()
            : $start->addDays(6);
    }

    public static function previousStart(string $type, CarbonImmutable $start): CarbonImmutable
    {
        return $type === Publication::TYPE_MONTH
            ? $start->subMonthNoOverflow()->startOfMonth()
            : $start->subWeek()->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * @return array{type: string, start: string, end: string, label: string}
     */
    public static function data(string $type, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'type' => $type,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $type === Publication::TYPE_MONTH ? $start->format('Y-m') : $start->format('o-\WW'),
        ];
    }

    public static function slug(string $type, CarbonImmutable $start): string
    {
        return $type === Publication::TYPE_MONTH
            ? 'month-' . $start->format('Y-m')
            : 'week-' . $start->format('Y-m-d');
    }

    /**
     * Период словами на языке страницы. Неделя — «26.10–01.11.2026» без перевода,
     * месяц — «Октябрь 2026» / «October 2026» / «ოქტომბერი 2026».
     *
     * @param  array{type: string, start: string, end: string}  $period
     */
    public static function label(array $period, string $locale): string
    {
        $start = CarbonImmutable::parse($period['start']);
        $end = CarbonImmutable::parse($period['end']);

        return $period['type'] === Publication::TYPE_MONTH
            ? $start->locale($locale)->translatedFormat('F Y')
            : $start->format('d.m') . '–' . $end->format('d.m.Y');
    }

    /**
     * Период из --period: для недели «2026-W44» или «2026-10-26», для месяца «2026-10».
     * Пустое значение — прошлая неделя или прошлый месяц: сводку собирают по расписанию.
     */
    public static function fromString(string $type, ?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $type === Publication::TYPE_MONTH
                ? CarbonImmutable::today()->subMonthNoOverflow()->startOfMonth()
                : CarbonImmutable::today()->subWeek()->startOfWeek(CarbonInterface::MONDAY);
        }

        try {
            if ($type === Publication::TYPE_MONTH) {
                return preg_match('/^\d{4}-\d{2}$/', $value)
                    ? CarbonImmutable::createFromFormat('!Y-m-d', $value . '-01')?->startOfMonth()
                    : null;
            }

            if (preg_match('/^(\d{4})-W(\d{1,2})$/', $value, $matches)) {
                return CarbonImmutable::now()
                    ->setISODate((int) $matches[1], (int) $matches[2])
                    ->startOfWeek(CarbonInterface::MONDAY);
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
                ? CarbonImmutable::parse($value)->startOfWeek(CarbonInterface::MONDAY)
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
