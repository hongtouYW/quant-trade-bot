<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\service\ElasticsearchService;

class ClearEsManhua extends Command
{
    protected function configure()
    {
        $this->setName('clear:manhua')->setDescription('Clear all manhua in Elasticsearch');
    }

    protected function execute(Input $input, Output $output)
    {
        $langs = ['zh', 'en'];

        foreach ($langs as $code) {
            $output->writeln("正在清空 Elasticsearch 中的 manhua 索引（语言：{$code}）...");
            ElasticsearchService::clearManhuaIndex($code);
            $output->writeln("✅ 索引 manhua_{$code} 清空完成！");
        }

        $output->writeln('清空完成 ✅');
    }
}
