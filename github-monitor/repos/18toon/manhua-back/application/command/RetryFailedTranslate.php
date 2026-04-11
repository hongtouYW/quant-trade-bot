<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Queue;

class RetryFailedTranslate extends Command
{
    protected function configure()
    {
        $this->setName('retry:translate_failed')
            ->setDescription('重试翻译失败的任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $maxRetries = 3;
        $count = 0;

        $manhuaRows = Db::name('manhua_tran_temps')
            ->whereIn('status', [2]) // 失败或未完成的任务
            ->where('retry_count', '<', $maxRetries)
            ->limit(100)
            ->select();

        foreach ($manhuaRows as $row) {
            // 增加 retry_count
            Db::name('manhua_tran_temps')->where('id', $row['id'])->inc('retry_count')->update([
                'status' => '0',
                'update_time' => time(),
                'error_message' => '',
            ]);

            // 重新加入队列
            Queue::push('app\index\job\TranslateManhuaFieldJob', [
                'manhua_id' => $row['manhua_id'],
                'lang_code' => $row['lang_code'],
                'field' => $row['field']
            ], 'tran_manhua_field');

            $count++;
        }

        // 处理章节字段失败记录（新增部分）
        $chapterRows = Db::name('capter_tran_temps')
            ->where('status', 2)
            ->where('retry_count', '<', $maxRetries)
            ->limit(100)
            ->select();

        foreach ($chapterRows as $row) {
            Db::name('capter_tran_temps')->where('id', $row['id'])->inc('retry_count')->update([
                'status' => '0',
                'update_time' => time(),
                'error_message' => '',
            ]);

            Queue::push('app\index\job\TranslateCapterFieldJob', [
                'capter_id' => $row['capter_id'],
                'lang_code' => $row['lang_code'],
                'field' => $row['field']
            ], 'tran_capter_field');

            $count++;
        }

        $normalRows = Db::table('translation_tasks')
            ->where('status', 2)
            ->where('retry_count', '<', $maxRetries)
            ->limit(100)
            ->select();

        foreach ($normalRows as $row) {
            Db::table('translation_tasks')->where('id', $row['id'])->inc('retry_count')->update([
                'status' => '0',
                'update_time' => time(),
                'error_message' => '',
            ]);

            Queue::push('app\index\job\TranslateFieldJob', [
                'task_id' => $row['id']
            ]);

            $count++;
        }

        $output->writeln("成功派发重试任务总数：{$count}");
    }
}
