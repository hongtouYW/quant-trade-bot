<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class CheckDuplicateChapters extends Command
{
    protected function configure()
    {
        $this->setName('check:duplicate-chapters')
            ->setDescription('检测重复章节并写入 duplicate_chapters 表');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("开始检测重复章节...");

        // 1. 清空旧数据（避免越来越大）
        Db::name('duplicate_chapters')->delete(true);

        // 2. 找出重复 (manhua_id, title)
        $duplicates = Db::name('capter')
            ->field("manhua_id, REPLACE(title, ' ', '') as norm_title, COUNT(*) as cnt")
            ->group('manhua_id, norm_title')
            ->having('cnt > 1')
            ->select();

        if (empty($duplicates)) {
            $output->writeln("没有重复章节");
            return;
        }

        $insertData = [];
        foreach ($duplicates as $dup) {
            // 查出所有重复记录（同一个 manhua_id，title 去掉空格后相同）
            $chapters = Db::name('capter')
                ->where('manhua_id', $dup['manhua_id'])
                ->whereRaw("REPLACE(title, ' ', '') = :norm_title", ['norm_title' => $dup['norm_title']])
                ->field('id,manhua_id,title')
                ->select();

            foreach ($chapters as $c) {
                $insertData[] = [
                    'manhua_id' => $c['manhua_id'],
                    'capter_id' => $c['id'],
                    'title'     => $c['title'],
                ];
            }
        }

        // 3. 批量写入 duplicate_chapters
        if (!empty($insertData)) {
            $chunks = array_chunk($insertData, 500); // 每批 500
            foreach ($chunks as $chunk) {
                Db::name('duplicate_chapters')->insertAll($chunk);
            }
            $output->writeln("已写入 " . count($insertData) . " 条重复章节数据");
        } else {
            $output->writeln("未找到重复章节");
        }
    }
}
