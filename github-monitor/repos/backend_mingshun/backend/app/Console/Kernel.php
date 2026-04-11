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
        $schedule->command('app:reset-daily')->daily();
        $schedule->command('app:calculate-user-task')->daily();
        $schedule->command('app:daily-report')->daily();
        $schedule->command('app:server-daily-report')->dailyAt('23:58');
        $schedule->command('app:monthly-report')->monthly();
        // $schedule->command('app:retry-failed')->dailyAt('05:00');
        // $schedule->command('app:retry-failed')->dailyAt('13:00');
        // $schedule->command('app:retry-failed')->dailyAt('21:00');
        // $schedule->command('app:subtitle-retry')->everyMinute();
        // $schedule->command('app:subtitle-resend')->everyMinute();
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
