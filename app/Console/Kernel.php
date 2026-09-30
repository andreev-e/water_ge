<?php

namespace App\Console;

use App\Console\Commands\CheckFailedJobs;
use App\Console\Commands\CountStats;
use App\Console\Commands\LoadEnergy;
use App\Console\Commands\LoadGas;
use App\Console\Commands\LoadGwp;
use App\Console\Commands\LoadWater;
use App\Console\Commands\MakeMailNotSubscribed;
use App\Console\Commands\PublishPendingToFacebook;
use App\Console\Commands\SendMail;
use App\Console\Commands\Translate;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(LoadWater::class)->everyFiveMinutes();
        $schedule->command(LoadGwp::class)->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
        $schedule->command(LoadEnergy::class)->everyFiveMinutes();
        $schedule->command(LoadGas::class)->everyFiveMinutes();
        $schedule->command(PublishPendingToFacebook::class)->everyMinute()->withoutOverlapping(10);
//        $schedule->command(Translate::class)->everyMinute();
        $schedule->command(CountStats::class)->hourly();
        $schedule->command(CheckFailedJobs::class)->hourly();
        $schedule->command(MakeMailNotSubscribed::class)->dailyAt('11:00');
        $schedule->command(SendMail::class)->everyMinute()->withoutOverlapping(10);
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
