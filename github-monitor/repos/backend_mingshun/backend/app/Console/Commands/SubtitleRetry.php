<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Models\ProjectRules;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class SubtitleRetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:subtitle-retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry subtitle every minutes with 50 from retry queue to prevent rsync close error';

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
            $id = $redis->lpop('subtitle_retry_queue');
            if (!$id) break;
            $redis->srem('subtitle_retry_ids_set', $id);

            $videoChoose = VideoChoose::find($id);
            if (!$videoChoose) continue;
            if (!in_array($videoChoose->status, [9, 10])) {
                continue;
            }
            if ($videoChoose->status == 9 && Carbon::parse($videoChoose->ai_at)->lt(now()->subDays(5)) === false) {
                continue;
            }
            
            $videoChoose = Helper::retrySubtitle($videoChoose);
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
        }
    }
}
