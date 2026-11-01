<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Данные для тестов сводок: сервисные центры, адреса, отключения, снимки статистики
 * и подписки. Пишем через построитель запросов — тестам нужны колонки, а не модель.
 */
trait CreatesOutageData
{
    protected function serviceCenter(string $name = 'თბილისი', ?string $nameEn = 'Tbilisi', ?string $nameRu = 'Тбилиси'): int
    {
        return DB::table('service_centers')->insertGetId([
            'name' => $name,
            'name_en' => $nameEn,
            'name_ru' => $nameRu,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function address(int $serviceCenterId, string $name = 'რუსთაველი 5'): int
    {
        return DB::table('addresses')->insertGetId([
            'service_center_id' => $serviceCenterId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  int[]  $addressIds
     */
    protected function outage(
        int $serviceCenterId,
        string $start,
        ?string $finish = '2026-10-27 12:00:00',
        string $type = 'water',
        bool $planned = true,
        int $customers = 100,
        array $addressIds = [],
    ): int {
        $id = DB::table('events')->insertGetId([
            'service_center_id' => $serviceCenterId,
            'start' => $start,
            'finish' => $finish,
            'type' => $type,
            'planned' => $planned,
            'effected_customers' => $customers,
            'total_addresses' => count($addressIds),
            'name' => 'Отключение',
            'name_en' => 'Outage',
            'name_ru' => 'Отключение',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($addressIds as $addressId) {
            DB::table('address_event')->insert(['address_id' => $addressId, 'event_id' => $id]);
        }

        return $id;
    }

    protected function userStat(string $date, int $users, int $subscriptions, int $filtered = 0): void
    {
        DB::table('user_stats')->insert([
            'date' => $date,
            'users' => $users,
            'subscriptions' => $subscriptions,
            'filtered' => $filtered,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function subscription(string $createdAt, int $botUserId = 1, ?int $serviceCenterId = null): void
    {
        DB::table('subscriptions')->insert([
            'bot_user_id' => $botUserId,
            'service_center_id' => $serviceCenterId ?? $this->serviceCenter(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
