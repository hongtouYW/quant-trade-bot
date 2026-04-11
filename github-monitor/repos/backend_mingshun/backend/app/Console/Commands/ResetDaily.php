<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CutRealServerStatusLog;
use App\Models\Log;
use App\Models\Project;
use App\Models\Video;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;

class ResetDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // chmod 777 for log 
        $todayDate = Carbon::now()->format('Y-m-d');
        $fileName = 'sendApi-' . $todayDate . '.log';
        $filePath = storage_path('logs/' . $fileName);
        if (!file_exists($filePath)) {
            file_put_contents($filePath, "Log file created on $todayDate\n");
            FacadesLog::channel('send_api')->info("Log file created: $filePath");
        }
        $command = 'chmod 777 ' . escapeshellarg($filePath);
        $output = [];
        exec($command, $output);
        FacadesLog::channel('send_api')->info("Chmod executed: $command -- " . json_encode($output));

        // chmod 777 for temp folder 
        $tempDirPath = public_path('storage/temp');
        if (!is_dir($tempDirPath)) {
            mkdir($tempDirPath, 0777, true); 
        }
        $tempDirCommand = 'chmod -R 777 ' . escapeshellarg($tempDirPath);
        $tempOutput = [];
        exec($tempDirCommand, $tempOutput);

        // delete log and temp file
        $command = 'find storage/app/public/temp/* -type f -mtime +1 -delete';
        $output = [];
        exec($command, $output);
        FacadesLog::channel('send_api')->info($command . "--" .json_encode($output));
        Log::where('created_at', '<', now()->subDays(30))->delete();
        CutRealServerStatusLog::where('created_at', '<', now()->subDays(7))->delete();

        // reset daily value
        Project::where('daily_cut', "!=", 0)->update([
            'daily_cut' => 0,
        ]);

        // Delete one years ago video and video choose
        // $videoIds = Video::where(function ($query){
        //         $query->where('status', 5)
        //             ->orWhere('status', 2)
        //             ->orWhere('status', 4);
        //     })
        //     ->where('created_at', '<=', Carbon::now()->subYear())
        //     ->orderBy('id', 'asc')
        //     ->limit(5000)
        //     ->pluck('id')
        //     ->toArray();

        // if (!empty($videoIds)) {
        //     DB::transaction(function () use ($videoIds) {
        //         VideoChoose::whereIn('video_id', $videoIds)->delete();
        //         Video::whereIn('id', $videoIds)->delete();
        //     });
        // }

        // $videoChooseIds = VideoChoose::where('created_at', '<=', Carbon::now()->subYear())
        //     ->orderBy('id', 'asc')
        //     ->limit(5000)
        //     ->pluck('id')
        //     ->toArray();
        // VideoChoose::whereIn('id', $videoChooseIds)->delete();
    }
}
