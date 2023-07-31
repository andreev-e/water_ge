<?php

namespace App\Console;

use App\Console\Commands\CheckFailedJobs;
use App\Console\Commands\CountStats;
use App\Console\Commands\LoadEnergy;
use App\Console\Commands\LoadGas;
use App\Console\Commands\LoadWater;
use App\Console\Commands\Translate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(LoadWater::class)->everyFiveMinutes();
        $schedule->command(LoadEnergy::class)->everyFiveMinutes();
        $schedule->command(LoadGas::class)->everyFiveMinutes();
        $schedule->command(Translate::class)->everyMinute();
        $schedule->command(CountStats::class)->hourly();
        $schedule->command(CheckFailedJobs::class)->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
