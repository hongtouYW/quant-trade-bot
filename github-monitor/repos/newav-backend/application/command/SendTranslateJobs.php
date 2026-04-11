<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\index\model\Zimu as ZimuModel;
use app\index\model\Video;

class SendTranslateJobs extends Command
{
    protected function configure()
    {
        $this->setName('zimu:send_translate')
            ->setDescription('Send translate jobs for videos whose transcribe callback was received 10+ minutes ago');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('=== Starting SendTranslateJobs ===');

        $delaySeconds = 60 * 60; // 1 hour
        $cutoff       = time() - $delaySeconds;

        // Find zimu_log rows: transcribe success, not yet sent to translate, older than 10min
        $rows = Db::name('zimu_log')
            ->where('type', 1)
            ->where('event', 2)
            ->whereNull('translate_sent_at')
            ->where('created_at', '<=', $cutoff)
            ->field('id, video_id')
            ->limit(15)
            ->select();

        if (empty($rows)) {
            $output->writeln('No pending translate jobs.');
            return;
        }

        foreach ($rows as $row) {
            $video = Video::find($row['video_id']);
            if (!$video || $video->zimu_status != 2) {
                // Already processed or video gone
                Db::name('zimu_log')->where('id', $row['id'])->update(['translate_sent_at' => time()]);
                $output->writeln("⏭ Video ID {$row['video_id']} skipped (status not 2).");
                continue;
            }

            if (ZimuModel::sendToThirdParty($video)) {
                Db::name('zimu_log')->where('id', $row['id'])->update(['translate_sent_at' => time()]);
                $output->writeln("✅ Video ID {$row['video_id']} translate job sent.");
            } else {
                $output->writeln("❌ Video ID {$row['video_id']} failed.");
            }
        }

        $output->writeln('=== SendTranslateJobs Completed ===');
    }
}
