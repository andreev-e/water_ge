<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOutageData;
use Tests\Concerns\PreparesDatabaseForTests;
use Tests\TestCase;

/**
 * Команда app:count-stats заполняет счётчики группировкой по связям: у центра —
 * адреса и отключения, у адреса — отключения, у отключения — адреса. Отключение
 * без адресов получает 0, а не остаётся пустым.
 */
class CountStatsCommandTest extends TestCase
{
    use CreatesOutageData;
    use PreparesDatabaseForTests;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabaseForTests();
    }

    public function test_counters_are_recalculated_from_the_relations(): void
    {
        $tbilisi = $this->serviceCenter('თბილისი');
        $batumi = $this->serviceCenter('ბათუმი', 'Batumi', 'Батуми');

        $first = $this->address($tbilisi, 'რუსთაველი 5');
        $second = $this->address($tbilisi, 'რუსთაველი 7');
        $third = $this->address($batumi, 'გამსახურდია 1');
        $idle = $this->address($tbilisi, 'რუსთაველი 9');

        $this->outage($tbilisi, '2026-10-26 09:00:00', addressIds: [$first, $second]);
        $this->outage($tbilisi, '2026-10-27 09:00:00', addressIds: [$first]);

        // У этих двух счётчика ещё нет — команда его и досчитает.
        $uncounted = $this->outage($batumi, '2026-10-28 09:00:00', addressIds: [$third]);
        $withoutAddresses = $this->outage($tbilisi, '2026-10-29 09:00:00');
        DB::table('events')->whereIn('id', [$uncounted, $withoutAddresses])->update(['total_addresses' => null]);

        // А этот счётчик пришёл из фида и больше числа связей — его команда не трогает.
        $fromFeed = $this->outage($tbilisi, '2026-10-30 09:00:00', addressIds: [$first]);
        DB::table('events')->where('id', $fromFeed)->update(['total_addresses' => 40]);

        $this->artisan('app:count-stats')->assertSuccessful();

        $this->assertSame(3, $this->value('service_centers', $tbilisi, 'total_addresses'));
        $this->assertSame(4, $this->value('service_centers', $tbilisi, 'total_events'));
        $this->assertSame(1, $this->value('service_centers', $batumi, 'total_addresses'));
        $this->assertSame(1, $this->value('service_centers', $batumi, 'total_events'));

        $this->assertSame(3, $this->value('addresses', $first, 'total_events'));
        $this->assertSame(1, $this->value('addresses', $second, 'total_events'));
        $this->assertSame(1, $this->value('addresses', $third, 'total_events'));
        $this->assertSame(0, $this->value('addresses', $idle, 'total_events'));

        $this->assertSame(1, $this->value('events', $uncounted, 'total_addresses'));
        $this->assertSame(0, $this->value('events', $withoutAddresses, 'total_addresses'));
        $this->assertSame(40, $this->value('events', $fromFeed, 'total_addresses'));
    }

    private function value(string $table, int $id, string $column): int
    {
        return (int) DB::table($table)->where('id', $id)->value($column);
    }
}
