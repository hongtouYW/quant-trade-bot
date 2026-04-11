<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    {
        // Define the log path
        $logFolder = storage_path('app/game/token/logs');
        $logFile = $logFolder . '/game_token_cleanup.log';

        // Make sure the folder exists
        if (!is_dir($logFolder)) {
            mkdir($logFolder, 0755, true);
        }

        // Schedule the cleanup command daily at midnight, log output safely
        $schedule->command('game:clean-tokens')
            ->daily()
            ->appendOutputTo($logFile);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        // Automatically loads all commands in app/Console/Commands
        $this->load(__DIR__.'/Commands');

        // Allows defining closure-based commands in routes/console.php
        require base_path('routes/console.php');
    }
}
