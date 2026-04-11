<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use app\index\model\Translate;

class TranslateVideos extends Command
{
    protected function configure()
    {
        $this->setName('translate:videos')
            ->setDescription('Auto-translate videos missing English titles');
    }

    protected function execute(Input $input, Output $output)
    {
        // Find all videos missing English title
        $videos = Db::name('video')
                    ->where(function ($query) {
                        $query->whereNull('title_en')
                            ->whereOr('title_en', '')
                            ->whereOr('title_twzh', '')
                            ->whereOr('title_twzh', 'NULL')
                            ->whereOr('title_en', 'like', '%Default Title%')
                            ->whereOr('title_en', 'like', '%Translation Error%');
                    })
                    ->limit(200)
                    ->column('id');


        if (empty($videos)) {
            $output->writeln('No untranslated videos found.');
            return;
        }

        $output->writeln('Found ' . count($videos) . ' untranslated videos.');

        // 2️⃣ Translate one by one
        foreach ($videos as $id) {
            $output->writeln("[{$id}] Translating...");
            try {
                $result = Translate::submit("video", (int)$id);
                if ($result) {
                    $output->writeln(" -> ✅ Success");
                } else {
                    $output->writeln(" -> ❌ Failed");
                }
            } catch (\Throwable $e) {
                $output->writeln(" -> ⚠️ Exception: " . $e->getMessage());
            }

            // optional: wait 1s to avoid API rate limits
            sleep(1);
        }

        $output->writeln('All done.');
    }
}
