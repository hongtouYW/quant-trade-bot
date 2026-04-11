<?php

namespace app\command;

use app\index\model\Comic;
use app\index\model\ComicTran;
use app\service\ElasticsearchService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class SyncManhuaToEs extends Command
{
    protected function configure()
    {
        $this->setName('sync:manhua')
            ->setDescription('Sync all manhua to Elasticsearch');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("开始同步漫画数据到 Elasticsearch 多语言索引...");

        // 1. 同步中文主表到 manhua_zh
        $zhData = Comic::select()->toArray();
        foreach ($zhData as $item) {
            ElasticsearchService::indexManhua([
                'id' => $item['id'],
                'title' => $item['title'],
                'desc' => $item['description']
            ], 'zh');
        }

        $output->writeln("✅ 中文同步完成");

        // 2. 同步 manhua_trans 到对应语言索引（非中文）
        $transData = ComicTran::select()->toArray();
        $countTrans = 0;

        foreach ($transData as $row) {
            $lang = strtolower($row['lang_code']);
            ElasticsearchService::indexManhua([
                'id' => $row['manhua_id'],
                'title' => $row['title'],
                'desc' => $row['description']
            ], $lang);
            $countTrans++;
        }

        $output->writeln("✅ 翻译语言同步完成");
        $output->writeln("全部同步完成 ✅");
    }
}
