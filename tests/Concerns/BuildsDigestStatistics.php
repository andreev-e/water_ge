<?php

namespace Tests\Concerns;

/**
 * Цифры сводки в том виде, в каком их отдаёт OutageDigest: неделя 26.10–01.11.2026,
 * три отключения, прошлый период — два. По ним пишется текст и рисуется страница.
 */
trait BuildsDigestStatistics
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function digestStatistics(array $overrides = []): array
    {
        return array_replace_recursive([
            'period' => ['type' => 'week', 'start' => '2026-10-26', 'end' => '2026-11-01', 'label' => '2026-W44'],
            'previous' => ['type' => 'week', 'start' => '2026-10-19', 'end' => '2026-10-25', 'label' => '2026-W43'],
            'reliable' => true,
            'outages' => [
                'total' => 3,
                'by_type' => ['water' => 2, 'energy' => 1, 'gas' => 0],
                'planned' => 2,
                'emergency' => 1,
                'unknown_kind' => 0,
                'emergency_share' => 0.333,
                'median_duration_hours' => 2.5,
                'longest' => [
                    'hours' => 5.0,
                    'type' => 'water',
                    'center' => ['ka' => 'თბილისი', 'en' => 'Tbilisi', 'ru' => 'Тбилиси'],
                ],
                'busiest_day' => ['date' => '2026-10-27', 'events' => 2],
                'addresses' => 2,
                'address_links' => 4,
                'customers' => 340,
                'top_centers' => [
                    [
                        'id' => 1,
                        'names' => ['ka' => 'თბილისი', 'en' => 'Tbilisi', 'ru' => 'Тбилиси'],
                        'events' => 3,
                        'addresses' => 2,
                    ],
                ],
                'top_addresses' => [
                    [
                        'id' => 1,
                        'names' => ['ka' => 'რუსთაველი 5', 'en' => 'Rustaveli 5', 'ru' => 'Rustaveli 5'],
                        'events' => 2,
                    ],
                ],
                'change_percent' => 50.0,
                'addresses_change_percent' => null,
                'duration_change_percent' => null,
            ],
            'audience' => [
                'users_start' => 90,
                'users_end' => 100,
                'users_change' => 10,
                'subscriptions_start' => 50,
                'subscriptions_end' => 60,
                'subscriptions_change' => 10,
                'filtered_end' => 5,
                'new_subscriptions' => 4,
            ],
        ], $overrides);
    }
}
