<?php

namespace App\Console\Commands;

use App\Models\Config;
use Illuminate\Console\Command;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Laravel\Facades\Telegram;

class ExportVideoChooseSuccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-video-choose-success {--month= : Specific month in Y-m format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export video choose success data to Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthOption = $this->option('month');

        if ($monthOption) {
            $currentDate = Carbon::parse($monthOption . '-01');
        } else {
            $currentDate = Carbon::now()->subMonth();
        }

        $firstDayLastMonth = $currentDate->copy()->startOfMonth();
        $lastDayLastMonth = $currentDate->copy()->endOfMonth();
        $monthName = $currentDate->copy()->format('Y年m月');

        $videoChoosesSuccess = VideoChoose::with('project')->whereBetween(DB::raw('DATE(callback_at)'), [$firstDayLastMonth, $lastDayLastMonth])
            ->where('status',7)
            ->get()->groupBy('project.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });

        $totalSuccess = 0;
        $string = $monthName . "月\n\n";
        foreach($videoChoosesSuccess as $projectName => $count){
            $string .= $projectName . " " . ($count?? 0) . "完成\n" ;
            $totalSuccess += $count;
        }

        $string .= "\n" ; 
        $string .= '总计 ' . $totalSuccess . "完成";
        
        $this->info($string);

        return Command::SUCCESS;
    }
}
