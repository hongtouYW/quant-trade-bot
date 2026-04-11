<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanGameTokenFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'game:clean-tokens';

    /**
     * The console command description.
     */
    protected $description = 'Delete old daily game request token files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        \Gamehelper::cleanOldFiles();

        $this->info('Old token files cleaned successfully.');

        return 0;
    }
}
