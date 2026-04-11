<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\VideoChoose;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SubtitleResend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:subtitle-resend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend subtitle every minutes with 50 from retry queue to prevent rsync close error';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         for ($i = 0; $i < 5; $i++) {
            $redis = Redis::connection('default');
            $result = $redis->select(3);
            if(!$result){
                throw new \Exception('Redis DB 错误');
            }
            $id = $redis->lpop('subtitle_resend_queue');
            if (!$id) break;
            $redis->srem('subtitle_resend_ids_set', $id);

            $videoChoose = VideoChoose::find($id);
            if (!$videoChoose) continue;
            if ($videoChoose->status != 12) continue;

            $videoChoose = Helper::resendSubtitle($videoChoose);
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
         }
        
    }
}
