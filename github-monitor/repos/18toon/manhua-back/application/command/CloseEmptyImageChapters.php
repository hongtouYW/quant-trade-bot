<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class CloseEmptyImageChapters extends Command
{
    protected function configure()
    {
        $this->setName('close:chapter-empty')
            ->setDescription('关闭封面图为空的章节，并同步关闭对应漫画的状态');
    }

    protected function execute(Input $input, Output $output)
    {
        // 查找封面图为空的章节
        $chapters = Db::name('capter')
            ->whereNull('image')
            ->where('switch', 1) // 只处理已开启的章节
            ->select();

        if (empty($chapters)) {
            $output->writeln('没有需要关闭的章节。');
            return;
        }

        $manhuaIds = [];

        foreach ($chapters as $chapter) {
            // 关闭章节的 switch 状态
            Db::name('capter')
                ->where('id', $chapter['id'])
                ->update(['switch' => 0]);

            $manhuaIds[] = $chapter['manhua_id'];
        }

        $manhuaIds = array_unique($manhuaIds);

        // 关闭相关漫画状态
        foreach ($manhuaIds as $mid) {
            Db::name('manhua')
                ->where('id', $mid)
                ->update(['status' => 0]);
        }

        $output->writeln('已关闭章节及对应漫画状态。');
    }
}
