<?php

namespace app\index\job;

use app\index\model\ComicTran;
use Exception;
use think\queue\Job;
use think\Db;

// php think queue:listen --queue=tran_manhua

class TranslatedManhuaJob
{
    public function fire(Job $job, $data)
    {
        $manhuaId = $data['manhua_id'];

        // 获取翻译临时表中指定 manhua_id 的所有记录
        $records = Db::name('manhua_tran_temps')
            ->where('manhua_id', $manhuaId)
            ->where('status', 1)
            ->select();

        if (empty($records)) {
            $job->delete();
            return;
        }

        $obj = new TranslateManhuaInit();
        $fields = $obj->fields; // 获取所有字段
        $languages = $obj->languages; // 获取所有语言

        // 按语言分组并检查每个语言的翻译完整性
        $grouped = [];
        foreach ($records as $item) {
            $lang = $item['lang_code'];
            if (!isset($languages[$lang])) {
                continue; // 跳过不支持的语言
            }

            if (!isset($grouped[$lang])) {
                $grouped[$lang] = [
                    'manhua_id' => $manhuaId,
                    'lang_code' => $lang,
                ] + array_fill_keys($fields, '');
            }

            $grouped[$lang][$item['field']] = $item['translated_text'];
        }

        // 筛选出所有字段翻译完成的语言
        $insertList = [];
        $completedLangs = [];
        foreach ($grouped as $lang => $data) {
            $isComplete = true;
            // foreach ($fields as $field) {
            //     if (empty($data[$field])) {
            //         $isComplete = false;
            //         break;
            //     }
            // }

            if ($isComplete) {
                $insertList[] = $data;
                $completedLangs[] = $lang;
            }
        }

        if (empty($insertList)) {
            $job->delete();
            return;
        }

        Db::startTrans();
        try {
            foreach ($insertList as $data) {
                $comicTran = ComicTran::where([
                    'manhua_id' => $data['manhua_id'],
                    'lang_code' => $data['lang_code'],
                ])->find();

                if ($comicTran) {
                    // 更新已有记录
                    $comicTran->save($data);
                } else {
                    // 插入新记录
                    ComicTran::create($data);
                }
            }

            // // 清空已完成语言的旧翻译记录
            // Db::name('manhua_trans')
            //     ->where('manhua_id', $manhuaId)
            //     ->where('lang_code', 'in', $completedLangs)
            //     ->delete();

            // // 批量写入已完成语言的翻译数据
            // Db::name('manhua_trans')->insertAll($insertList);

            // 清除已完成语言的临时翻译记录
            Db::name('manhua_tran_temps')
                ->where('manhua_id', $manhuaId)
                ->where('lang_code', 'in', $completedLangs)
                ->delete();

            // 检查是否所有语言的翻译都已完成
            $remaining = Db::name('manhua_tran_temps')
                ->where('manhua_id', $manhuaId)
                ->count();

            if ($remaining == 0) {
                // 所有语言翻译完成，更新主表状态
                Db::name('manhua')->where('id', $manhuaId)->update([
                    'translate_status' => 1,
                ]);
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            save_log('同步翻译数据失败: ' . $e->getMessage(), 'translate');
            $job->release(10); // 异常时延迟重试
            return;
        }

        // 如果还有未完成的翻译，释放任务以便后续继续处理
        $remaining = Db::name('manhua_tran_temps')
            ->where('manhua_id', $manhuaId)
            ->count();

        if ($remaining > 0) {
            $job->release(10); // 延迟10秒后重试
        } else {
            $job->delete(); // 所有翻译完成，删除任务
        }
    }

    public function failed($data)
    {
        save_log('同步翻译数据失败: ' . json_encode($data), 'translate');
    }
}
