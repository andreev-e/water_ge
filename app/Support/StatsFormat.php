<?php

namespace App\Support;

/**
 * Числа на странице и в тексте сводки: в русском и грузинском разряды
 * разделяются пробелом, в английском — запятой.
 */
class StatsFormat
{
    public static function number(int|float|null $value, string $locale, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }

        $english = $locale === Locale::ENGLISH;

        return number_format(
            (float) $value,
            $decimals,
            $english ? '.' : ',',
            $english ? ',' : ' ',
        );
    }

    /** Доля 0..1 — «12%» или «12,4%». */
    public static function percent(?float $share, string $locale): string
    {
        return $share === null ? '—' : self::number(round($share * 100, 1), $locale, self::decimals(round($share * 100, 1))) . '%';
    }

    /** Изменение в процентах — «+12%», «-8,5%»; null — «—». */
    public static function change(?float $percent, string $locale): string
    {
        if ($percent === null) {
            return '—';
        }

        $rounded = round($percent, 1);
        // Округление −0,04 даёт −0.0, а number_format напечатал бы «-0%».
        $rounded = $rounded == 0.0 ? 0.0 : $rounded;
        $sign = $rounded > 0 ? '+' : '';

        return $sign . self::number($rounded, $locale, self::decimals($rounded)) . '%';
    }

    /** Длительность в часах — «14,5 ч»; единицу измерения даёт перевод. */
    public static function hours(?float $hours, string $locale): string
    {
        return $hours === null
            ? '—'
            : self::number(round($hours, 1), $locale, self::decimals(round($hours, 1))) . ' ' . __('web.publications.hours', [], $locale);
    }

    private static function decimals(float $value): int
    {
        return floor($value) === $value ? 0 : 1;
    }
}
