<?php

namespace App\Services\Publications;

use App\Support\Locale;
use App\Support\StatsFormat;
use Carbon\CarbonImmutable;

/**
 * Текст сводки на языках сайта из готовых цифр OutageDigest. Шаблоны живут
 * в resources/lang/web.php, поэтому формулировки можно править без выкладки кода,
 * а числа подставляются уже отформатированными под язык страницы.
 */
class DigestWriter
{
    /**
     * @param  array<string, mixed>  $statistics
     * @return array<string, array{title: string, excerpt: string, body: string}>
     */
    public function write(array $statistics): array
    {
        $texts = [];

        foreach (Locale::WEB as $locale) {
            $texts[$locale] = [
                'title' => $this->title($statistics, $locale),
                'excerpt' => $this->excerpt($statistics, $locale),
                'body' => implode("\n\n", $this->paragraphs($statistics, $locale)),
            ];
        }

        return $texts;
    }

    /** @param array<string, mixed> $statistics */
    private function title(array $statistics, string $locale): string
    {
        $period = DigestPeriod::label($statistics['period'], $locale);
        $total = (int) $statistics['outages']['total'];

        if ($total === 0) {
            return __('web.digest.title_empty', ['period' => $period], $locale);
        }

        return __('web.digest.title', [
            'period' => $period,
            'count' => StatsFormat::number($total, $locale),
        ], $locale);
    }

    /** @param array<string, mixed> $statistics */
    private function excerpt(array $statistics, string $locale): string
    {
        $period = DigestPeriod::label($statistics['period'], $locale);
        $outages = $statistics['outages'];
        $total = (int) $outages['total'];

        if ($total === 0) {
            return __('web.digest.excerpt_empty', ['period' => $period], $locale);
        }

        $replace = [
            'period' => $period,
            'count' => StatsFormat::number($total, $locale),
            'center' => $this->centerLine($outages, $locale),
        ];

        if ($outages['change_percent'] === null) {
            return __('web.digest.excerpt_no_change', $replace, $locale);
        }

        return __('web.digest.excerpt', $replace + ['change' => StatsFormat::change($outages['change_percent'], $locale)], $locale);
    }

    /**
     * Абзацы текста: общая картина, виды, география, длительность, аудитория.
     *
     * @param  array<string, mixed>  $statistics
     * @return string[]
     */
    private function paragraphs(array $statistics, string $locale): array
    {
        $outages = $statistics['outages'];
        $total = (int) $outages['total'];

        if ($total === 0) {
            return [__('web.digest.no_data', ['period' => DigestPeriod::label($statistics['period'], $locale)], $locale)];
        }

        $paragraphs = [];
        $count = StatsFormat::number($total, $locale);

        $paragraphs[] = $outages['change_percent'] === null
            ? __('web.digest.summary_no_change', ['count' => $count], $locale)
            : __('web.digest.summary', ['count' => $count, 'change' => StatsFormat::change($outages['change_percent'], $locale)], $locale);

        $byType = $outages['by_type'] ?? [];
        $paragraphs[] = __('web.digest.kinds', [
            'water' => StatsFormat::number($byType['water'] ?? 0, $locale),
            'energy' => StatsFormat::number($byType['energy'] ?? 0, $locale),
            'gas' => StatsFormat::number($byType['gas'] ?? 0, $locale),
            'planned' => StatsFormat::number($outages['planned'], $locale),
            'emergency' => StatsFormat::number($outages['emergency'], $locale),
        ], $locale);

        if (!empty($outages['top_centers'])) {
            $paragraphs[] = __('web.digest.geography', ['centers' => $this->centersLine($outages['top_centers'], $locale)], $locale);
        }

        if (!empty($outages['busiest_day'])) {
            $paragraphs[] = __('web.digest.busiest', [
                'date' => CarbonImmutable::parse($outages['busiest_day']['date'])->locale($locale)->translatedFormat('j F'),
                'count' => StatsFormat::number($outages['busiest_day']['events'], $locale),
            ], $locale);
        }

        $paragraphs[] = __('web.digest.addresses', [
            'addresses' => StatsFormat::number($outages['addresses'], $locale),
            'customers' => StatsFormat::number($outages['customers'], $locale),
        ], $locale);

        if ($outages['median_duration_hours'] !== null && !empty($outages['longest'])) {
            $paragraphs[] = __('web.digest.duration', [
                'median' => StatsFormat::hours($outages['median_duration_hours'], $locale),
                'center' => $this->centerName($outages['longest']['center'], $locale),
                'type' => __('web.series.' . $outages['longest']['type'], [], $locale),
                'longest' => StatsFormat::hours($outages['longest']['hours'], $locale),
            ], $locale);
        }

        $audience = $statistics['audience'];
        if ($audience['users_end'] !== null) {
            $paragraphs[] = __('web.digest.audience', [
                'users' => StatsFormat::number($audience['users_end'], $locale),
                'subscriptions' => StatsFormat::number($audience['subscriptions_end'], $locale),
                'new' => StatsFormat::number($audience['new_subscriptions'], $locale),
            ], $locale);
        }

        if (!$statistics['reliable']) {
            $paragraphs[] = __('web.digest.preliminary', [], $locale);
        }

        return $paragraphs;
    }

    /**
     * Три центра, где отключали чаще всего: «Тбилиси — 41, Батуми — 17».
     *
     * @param  array<int, array{names: array<string, string>, events: int}>  $centers
     */
    private function centersLine(array $centers, string $locale): string
    {
        return implode(', ', array_map(
            fn (array $center) => $this->centerName($center['names'], $locale) . ' — ' . StatsFormat::number($center['events'], $locale),
            array_slice($centers, 0, 3),
        ));
    }

    /** @param array<string, mixed> $outages */
    private function centerLine(array $outages, string $locale): string
    {
        return empty($outages['top_centers'])
            ? '—'
            : $this->centerName($outages['top_centers'][0]['names'], $locale);
    }

    /** @param array<string, string> $names */
    private function centerName(array $names, string $locale): string
    {
        return (string) ($names[$locale] ?? $names[Locale::WEB_DEFAULT] ?? '');
    }
}

