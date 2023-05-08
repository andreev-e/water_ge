<?php

namespace App\Console;

use App\Console\Commands\LoadSchedule;
use App\Console\Commands\Translate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        define('ARTISAN_BINARY', '/var/www/water_andreev/data/www/water.andreev-e.ru/artisan');
        $schedule->command(LoadSchedule::class)->everyFiveMinutes();
        $schedule->command(Translate::class)->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
