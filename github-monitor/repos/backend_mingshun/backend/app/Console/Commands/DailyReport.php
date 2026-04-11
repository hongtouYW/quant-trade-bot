<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Config;
use App\Models\Photo;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Telegram\Bot\Laravel\Facades\Telegram;

class DailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Video
        $yesterday = Carbon::now()->subDay();
        $formattedDate = Carbon::now()->subDay()->format('Y-m-d');
        $videoChoosesSuccess = VideoChoose::with('project')
            ->whereDate('callback_at', $yesterday)
            ->whereIn('status',[4,5,7])
            ->get()->groupBy('project.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });
        $videoChoosesPending = VideoChoose::with('project')
            ->where('status',2)->get()->groupBy('project.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });
        $videoChoosesSubtitlePending = VideoChoose::with('project')
            ->whereIn('status',[9,11])->get()->groupBy('project.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });
        $videoChoosesSubtitleFailed = VideoChoose::with('project')
            ->whereIn('status',[10,12,13])->get()->groupBy('project.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });
        $temp = [];
        $totalSuccess = 0;
        foreach($videoChoosesSuccess as $projectName => $count){
            $temp[$projectName]['success'] = $count;
            $totalSuccess += $count;
        }
        $totalPending = 0;
        foreach($videoChoosesPending as $projectName => $count){
            $temp[$projectName]['pending'] = $count;
            $totalPending += $count;
        }
        $totalSubtitlePending = 0;
        foreach($videoChoosesSubtitlePending as $projectName => $count){
            $temp[$projectName]['videoChoosesSubtitlePending'] = $count;
            $totalSubtitlePending += $count;
        }
        $totalFailedPending = 0;
        foreach($videoChoosesSubtitleFailed as $projectName => $count){
            $temp[$projectName]['videoChoosesSubtitleFailed'] = $count;
            $totalFailedPending += $count;
        }
        $string = $formattedDate . "\n\n";
        foreach($temp as $projectName => $count){
            $string .= $projectName . " \n" . ($count['success']?? 0) . "完成 \n" 
            . ($count['pending']?? 0) . "切片中\n";
            if($count['videoChoosesSubtitlePending']?? 0){
                $string .= ($count['videoChoosesSubtitlePending']?? 0) . "生成字幕中\n";
            }
            if($count['videoChoosesSubtitleFailed']?? 0){
                $string .= ($count['videoChoosesSubtitleFailed']?? 0) . "生成字幕失败\n";
            }
            $string .= "\n";
        }
        
        $string .= "\n" ; 
        $string .= '总计 ' . $totalSuccess . "完成\n" . $totalPending . "切片中\n"
        . $totalSubtitlePending . "生成字幕中\n" . $totalFailedPending . "生成字幕失败\n";
        $chatId = Config::getCachedConfig('telegram_bot_chat_id');

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>  $string,
        ]);

        // Photo
        $yesterday = Carbon::now()->subDay();
        $formattedDate = Carbon::now()->subDay()->format('Y-m-d');
        $videoChoosesSuccess = Photo::with('projects')->whereDate('created_at', $yesterday)
            ->whereNotIn('status',[2])
            ->get()->groupBy('projects.name')
            ->map(function($videoChoose) {
                return $videoChoose->count();
            });
        $temp = [];
        $totalSuccess = 0;
        foreach($videoChoosesSuccess as $projectName => $count){
            $temp[$projectName]['success'] = $count;
            $totalSuccess += $count;
        }
        $string = $formattedDate . "\n\n";
        foreach($temp as $projectName => $count){
            $string .= $projectName . " " . ($count['success']?? 0) . "完成\n";
        }
        
        $string .= "\n" ; 
        $string .= '总计 ' . $totalSuccess . "完成 ";
        $chatId = Config::getCachedConfig('telegram_bot_chat_id');
        
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>  $string,
        ]);
    }
}
