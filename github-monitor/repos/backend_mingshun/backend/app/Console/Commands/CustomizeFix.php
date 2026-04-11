<?php

namespace App\Console\Commands;

use App\Models\VideoChoose;
use Illuminate\Console\Command;

class CustomizeFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:customize-fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Customize Fix';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = VideoChoose::where('project_id', 36)
            ->whereIn('status', [2, 8])
            ->update([
                'status' => 3,
                'cut_callback_msg' => 'project server deny',
            ]);

        $this->info("Updated {$count} video choose records.");
    }
}