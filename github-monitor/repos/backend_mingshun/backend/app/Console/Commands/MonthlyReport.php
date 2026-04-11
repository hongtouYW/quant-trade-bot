<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helper;
use App\Models\Config;
use App\Models\Photo;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class MonthlyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:monthly-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monthly Report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Video
        $currentDate = Carbon::now();
        $firstDayLastMonth = $currentDate->subMonth()->startOfMonth();
        $lastDayLastMonth = $currentDate->copy()->endOfMonth();

        $monthName =  $currentDate->copy()->format('m');

        $videoChoosesSuccess = Photo::with('projects')->whereBetween(DB::raw('DATE(created_at)'), [$firstDayLastMonth, $lastDayLastMonth])
            ->whereNotIn('status',[2])
            ->get()->groupBy('projects.name')
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

        $chatId = Config::getCachedConfig('telegram_bot_chat_id');
        
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>  $string,
        ]);

        // Photo
        $currentDate = Carbon::now();
        $firstDayLastMonth = $currentDate->subMonth()->startOfMonth();
        $lastDayLastMonth = $currentDate->copy()->endOfMonth();

        $monthName =  $currentDate->copy()->format('m');

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
        $chatId = Config::getCachedConfig('telegram_bot_chat_id');
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>  $string,
        ]);

        // Special Montly Report to Calculate 短视频 video duration and its amount
        $fileName = Helper::export();
        $filePath = storage_path('app/public/temp/' . $fileName);
        Telegram::sendDocument([
            'chat_id' => $chatId,
            'document' => InputFile::create($filePath, $fileName) ,
            'caption' => "",
        ]);
    }
}
