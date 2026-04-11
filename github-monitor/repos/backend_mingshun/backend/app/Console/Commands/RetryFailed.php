<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RetryFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry Failed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // recut 
        VideoChoose::where(function ($query){
                $query->where(function ($q) {
                    $q->where('status', 8)
                        ->where('send_callback_msg','参数格式不正确');
                })
                ->orWhere(function ($q) {
                    $q->where('status', 10)
                        ->where(function ($qqq) {
                            $qqq->where('cut_callback_msg', 'info data is missing')
                                ->orWhere('subtitle_callback_msg', 'backup is expired');
                        });
                        
                })
                ->orWhere(function ($q){
                    $q->where('status', 3)
                        ->where('cut_at', '<=', Carbon::now()->subDays(3))
                        ->where(function ($qqq) {
                            $qqq->where('cut_callback_msg', 'like','%send error%')
                            ->orWhere('cut_callback_msg', '网络波动，无法检查视频资源')
                            ->orWhere('cut_callback_msg', 'like','%cURL error%')
                            ->orWhere('cut_callback_msg', '已切片')
                            ->orWhere('cut_callback_msg', 'like','%thread download error%')
                            ->orWhere('cut_callback_msg', '视频资料有问题清重新切片');
                        });
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($videoChooses) {
               foreach ($videoChooses as $videoChoose) {
                    $videoChoose->sync_callback_msg = '';
                    $videoChoose->cut_callback_msg = '';
                    $videoChoose->subtitle_callback_msg = '';
                    $videoChoose->save();

                    if ($videoChoose->projectRule) {
                        $temp = new \stdClass();
                        $temp->theme = $videoChoose->types?->pluck('id')->toArray();
                        $temp->rule = $videoChoose->project_rule_id;
                        VideoChoose::cutStatus($temp, $videoChoose->id, 1, 0);
                    }
                }
            });

        // // retry subtitle
        // VideoChoose::where(function ($query){
        //         // cut callback missing when subtitle callback
        //         $query->where(function ($q){
        //             $q->where('status', 10)
        //                 ->where(function ($qq) {
        //                     $qq->where('subtitle_callback_msg', 'like', '%Job with this video_id already exists%')
        //                          ->orWhere('subtitle_callback_msg', 'like', '%Rate limit exceeded%')
        //                          ->orWhere('subtitle_callback_msg', 'like', '%send error%')
        //                          ->orWhere('subtitle_callback_msg', 'like', '%call error%')
        //                          ->orWhere('subtitle_callback_msg', 'like', '%failed%')
        //                          ->orWhere('subtitle_callback_msg', 'like', '%/transcribes": EOF%');
        //                 });
        //         })
        //         ->orWhere(function ($q) {
        //             $q->where('status', 9)
        //                 ->where('ai_at', '<=', Carbon::now()->subDays(14));
        //         });
        //     })
            
        //     ->orderBy('id')
        //     ->chunkById(100, function ($videoChooses) {
        //         $redis = Redis::connection('default');
        //         $redis->select(3);

        //         foreach ($videoChooses as $videoChoose) {
        //             $wasAdded = $redis->sadd('subtitle_retry_ids_set', $videoChoose->id);
        //             if ($wasAdded) {
        //                 $redis->rpush('subtitle_retry_queue', $videoChoose->id);
        //             }
        //         }
        //     });

        // // resend subtitle
        // VideoChoose::where('status', 12)
        //     ->orderBy('id')
        //     ->chunkById(100, function ($videoChooses) {
        //         $redis = Redis::connection('default');
        //         $redis->select(3);

        //         foreach ($videoChooses as $videoChoose) {
        //             $wasAdded = $redis->sadd('subtitle_resend_ids_set', $videoChoose->id);
        //             if ($wasAdded) {
        //                 $redis->rpush('subtitle_resend_queue', $videoChoose->id);
        //             }
        //         }
        //     });

        VideoChoose::where('status', 8)->where('send_callback_msg','数据已存在')
            ->orderBy('id')
            ->update([
                'status' => 7
            ]);
            
        // chmod 777 for temp folder 
        $tempDirPath = public_path('storage/temp');
        if (!is_dir($tempDirPath)) {
            mkdir($tempDirPath, 0777, true); 
        }
        $tempDirCommand = 'chmod -R 777 ' . escapeshellarg($tempDirPath);
        $tempOutput = [];
        exec($tempDirCommand, $tempOutput);
    }
}
