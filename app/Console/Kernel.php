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
        $schedule->command(LoadSchedule::class)->everyFiveMinutes()->runInBackground();
        $schedule->command(Translate::class)->hourly()->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
