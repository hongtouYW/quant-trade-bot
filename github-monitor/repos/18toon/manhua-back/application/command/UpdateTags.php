<?php

namespace app\command;

use app\index\model\Tags;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class UpdateTags extends Command
{
    protected function configure()
    {
        $this->setName('update:tags')
            ->setDescription('Update English translation for tags from Excel file, delete if both names empty');
    }

    protected function execute(Input $input, Output $output)
    {
        $filePath = env('root_path') . 'public/tags.xlsx'; // Excel 文件路径

        if (!file_exists($filePath)) {
            $output->writeln("<error>File not found: {$filePath}</error>");
            return;
        }

        // 读取 Excel
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // 跳过表头，从第二行开始
        foreach ($rows as $index => $row) {
            if ($index === 0) continue;

            [$tagId, $nameCn, $nameEn, $tagUsageCount] = $row;

            if (empty($tagId)) {
                $output->writeln("Skipping row {$index} (tag_id empty)");
                continue;
            }

            $tag = Tags::get($tagId);
            if (!$tag) {
                $output->writeln("<comment>Tag not found: {$tagId}</comment>");
                continue;
            }

            // 如果 name_cn 和 name_en 都为空，删除 tags + tag_trans
            if (empty($nameCn) && empty($nameEn)) {
                $tag->delete();
                Db::table('tag_trans')->where('tag_id', $tagId)->delete();
                $output->writeln("<info>Deleted tag_id={$tagId} (tags + tag_trans)</info>");
                continue;
            }

            // 只更新英文翻译
            if (!empty($nameEn)) {
                $trans = Db::table('tag_trans')->where('tag_id', $tagId)
                    ->where('lang_code', 'en')
                    ->find();

                if ($trans) {
                    Db::table('tag_trans')
                        ->where('id', $trans['id'])
                        ->update(['name' => $nameEn]);
                    $output->writeln("Updated tag_trans tag_id={$tagId}, name_en={$nameEn}");
                } else {
                    Db::table('tag_trans')->create([
                        'tag_id'    => $tagId,
                        'lang_code' => 'en',
                        'name'      => $nameEn,
                    ]);
                    $output->writeln("Inserted tag_trans tag_id={$tagId}, name_en={$nameEn}");
                }
            } else {
                $output->writeln("Skipped tag_trans update tag_id={$tagId} (name_en empty)");
            }
        }

        $output->writeln("<info>Tag update completed.</info>");
    }
}
