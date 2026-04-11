<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Queue;

class RetryFailedTranslateImg extends Command
{
    protected function configure()
    {
        $this
            ->setName('retry:translate_img_failed')
            ->setDescription('重新翻译所有未成功的漫画章节，并删除已不存在漫画的章节');
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取所有 translate_img != 2 的章节
        $chapters = Db::name('capter')
            ->where('translate_img', '<>', 2)
            ->select();

        if (empty($chapters)) {
            $output->writeln('没有需要重新翻译的章节');
            return;
        }

        foreach ($chapters as $capter) {
            // 检查漫画是否存在
            $manhuaExists = Db::name('manhua')->where('id', $capter['manhua_id'])->count();

            if (!$manhuaExists) {
                // 删除章节
                Db::name('capter')->where('id', $capter['id'])->delete();

                // 删除章节对应的翻译
                Db::name('capter_trans')->where('capter_id', $capter['id'])->delete();
                
                $output->writeln("章节 {$capter['id']} 对应漫画不存在，已删除");
                continue;
            }

            // 章节存在且未翻译完成，重新入队
            Queue::push('app\index\job\TranslateCapterImgJob', [
                'capter_id' => $capter['id']
            ], 'tran_capter_img');

            // 更新状态为翻译中
            Db::name('capter')->where('id', $capter['id'])->update([
                'translate_img' => 1
            ]);

            $output->writeln("章节 {$capter['id']} 已加入翻译队列");
        }

        $output->writeln('操作完成！');
    }
}
