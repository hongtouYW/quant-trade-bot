<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\index\model\Video;
use app\index\model\Zimu;
use app\index\model\Configs;

class UpdateZimu extends Command
{
    protected function configure()
    {
        $this->setName('update:zimu')
             ->setDescription('Send subtitle translation requests for pending videos');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('=== Starting UpdateZimu Command ===');

        // zimu status
        // 0 = 提取字幕
        // 1 = 字幕提取中
        // 2 = 翻译字幕(字幕提取成功)
        // 3 = 字幕翻译中
        // 4 = 字幕翻译完成
        // 8 = 提取字幕失败
        // 9 = 字幕翻译失败

        $delayHours     = Configs::get('video_delay_hours', 12);
        $delayTimestamp = time() - ($delayHours * 3600);
        $videos = Video::where('status', 1)
                        ->where('zimu_status', 0)
                        ->where('insert_time', '<=', $delayTimestamp)
                        ->limit(8)
                        ->select();

        if ($videos->isEmpty()) {
            $output->writeln('No pending videos found.');
            return;
        }

        foreach ($videos as $video) {
            if (Zimu::sendTranscribeJob($video->id)) {
                $output->writeln("✅ Video ID {$video->id} transcribe job sent successfully.");
            } else {
                $output->writeln("❌ Video ID {$video->id} failed.");
            }
        }

        $output->writeln('=== UpdateZimu Command Completed ===');
    }
}
