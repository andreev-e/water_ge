<?php

namespace App\Console;

use App\Console\Commands\LoadSchedule;
use App\Console\Commands\Translate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    const  ARTISAN_BINARY = '/var/www/water_andreev/data/www/water.andreev-e.ru/artisan';

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(LoadSchedule::class)->everyFiveMinutes();
        $schedule->command(Translate::class)->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
