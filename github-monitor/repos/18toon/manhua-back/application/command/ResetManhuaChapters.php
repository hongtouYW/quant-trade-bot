<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class ResetManhuaChapters extends Command
{
    protected function configure()
    {
        $this->setName('reset:manhua-chapters')
            ->setDescription('Reset all manhua chapters and enable only translated ones in order')
            ->addOption('manhua', null, \think\console\input\Option::VALUE_OPTIONAL, 'Specify manhua_id');
    }

    protected function execute(Input $input, Output $output)
    {
        $manhuaId = $input->getOption('manhua');

        $output->writeln("Starting reset of chapters" . ($manhuaId ? " for manhua ID $manhuaId" : ""));

        $manhuaList = Db::name('manhua')
            ->when($manhuaId, function ($query) use ($manhuaId) {
                $query->where('id', $manhuaId);
            })
            ->select();

        foreach ($manhuaList as $manhua) {
            $id = $manhua['id'];

            // 重置所有章节
            Db::name('capter')->where('manhua_id', $id)->update([
                'switch'       => 0,
                'audit_status' => 0,
            ]);

            $chapters = Db::name('capter')
                ->where('manhua_id', $id)
                ->order('sort', 'asc')
                ->select();

            $canOpen = true;
            $openChapterIds = [];

            foreach ($chapters as $chapter) {
                if ($canOpen && $chapter['translate_img'] == 2) {
                    $openChapterIds[] = $chapter['id'];
                } else {
                    $canOpen = false;
                }
            }

            // 批量开启可用章节并设置 audit_status=1
            if (!empty($openChapterIds)) {
                Db::name('capter')->whereIn('id', $openChapterIds)->update([
                    'switch'       => 1,
                    'audit_status' => 1,
                ]);
            }

            // 判断封面是否齐全
            $hasCover = !empty($manhua['cover']) && !empty($manhua['cover_horizontal']);

            // 更新 manhua 状态
            $status = (!empty($openChapterIds) && $hasCover) ? 1 : 0;
            Db::name('manhua')->where('id', $id)->update([
                'status' => $status,
                'audit_user_id' => $status ? 1 : 0, // 系统审核
            ]);

            $output->writeln("Manhua ID $id: " . count($openChapterIds) . " chapters opened. Status=$status");
        }

        $output->writeln("Reset process completed.");
    }
}
