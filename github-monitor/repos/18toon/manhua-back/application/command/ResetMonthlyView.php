<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class ResetMonthlyView extends Command
{
    protected function configure()
    {
        $this->setName('reset:monthly_view')
            ->setDescription('Clear monthly monthly_view, monthly_mark, monthly_subscribe for manhua & capter tables');
    }

    protected function execute(Input $input, Output $output)
    {
        // 清空漫画 monthly_* 字段
        Db::name('manhua')
            ->where('1=1')
            ->update([
                'monthly_view' => 0,
                'monthly_mark' => 0,
                'monthly_subscribe' => 0,
            ]);

        // 清空章节 monthly_view
        Db::name('capter')
            ->where('1=1')
            ->update([
                'monthly_view' => 0,
            ]);

        $output->writeln("monthly_* fields reset on manhua & capter successfully!");
    }
}
