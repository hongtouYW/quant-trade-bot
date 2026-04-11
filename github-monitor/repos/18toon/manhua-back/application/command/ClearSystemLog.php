<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class ClearSystemLog extends Command
{
    protected function configure()
    {
        $this->setName('clear:system_log')
            ->setDescription('清除 system_log 表中一个月前的数据');
    }

    protected function execute(Input $input, Output $output)
    {
        $cutoff = strtotime('-1 month');

        $count = Db::table('system_log')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $output->writeln("已删除 {$count} 条日志记录（{$cutoff} 之前的数据）");
    }
}
