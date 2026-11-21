<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Счётчики для страниц сервисных центров, адресов и отключений.
 *
 * Считаем группировкой в базе. Прежний вариант шёл по каждой строке и делал count()
 * (64 тыс. адресов, 144 тыс. отключений): на рабочей базе он не укладывался в час,
 * запуски по расписанию копились и держали процессор. Группировка и UPDATE ... FROM
 * считают то же самое за секунды.
 */
class CountStats extends Command
{
    protected $signature = 'app:count-stats';

    protected $description = 'Counts addresses and outages of centers, addresses and outages';

    public function handle(): void
    {
        // Всё в одной транзакции: страницы не должны увидеть обнулённые счётчики.
        DB::transaction(function () {
            $this->countServiceCenters();
            $this->countAddresses();
            $this->countEvents();
        });
    }

    /** У центра — сколько у него адресов и отключений. */
    private function countServiceCenters(): void
    {
        DB::update('UPDATE service_centers SET total_addresses = 0, total_events = 0');

        DB::update(
            'UPDATE service_centers AS sc SET total_addresses = counted.total
               FROM (SELECT service_center_id AS id, COUNT(*) AS total FROM addresses GROUP BY service_center_id) AS counted
              WHERE counted.id = sc.id'
        );

        DB::update(
            'UPDATE service_centers AS sc SET total_events = counted.total
               FROM (SELECT service_center_id AS id, COUNT(*) AS total FROM events GROUP BY service_center_id) AS counted
              WHERE counted.id = sc.id'
        );
    }

    /** У адреса — сколько отключений через него прошло. */
    private function countAddresses(): void
    {
        DB::update('UPDATE addresses SET total_events = 0');

        DB::update(
            'UPDATE addresses AS a SET total_events = counted.total
               FROM (SELECT address_id AS id, COUNT(*) AS total FROM address_event GROUP BY address_id) AS counted
              WHERE counted.id = a.id'
        );
    }

    /** У отключения — сколько адресов затронуто; считаем только там, где счётчика ещё нет. */
    private function countEvents(): void
    {
        DB::update(
            'UPDATE events AS e SET total_addresses = counted.total
               FROM (SELECT event_id AS id, COUNT(*) AS total FROM address_event GROUP BY event_id) AS counted
              WHERE counted.id = e.id AND e.total_addresses IS NULL'
        );

        // Отключение без адресов: NULL → 0, как раньше давал count() в цикле.
        DB::update('UPDATE events SET total_addresses = 0 WHERE total_addresses IS NULL');
    }
}
