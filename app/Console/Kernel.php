<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Archive expired blocked accounts daily at 2 AM
        $schedule->command('app:archive-expired-blocked-accounts')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Unarchive expired blocked accounts daily at 2:30 AM
        $schedule->command('app:unarchive-expired-blocked-accounts')
                 ->dailyAt('02:30')
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
