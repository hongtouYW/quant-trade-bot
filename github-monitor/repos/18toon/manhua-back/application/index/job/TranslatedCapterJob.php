<?php

namespace app\index\job;

use app\index\model\ChapterTran;
use Exception;
use think\queue\Job;
use think\Db;

// php think queue:listen --queue=tran_capter

class TranslatedCapterJob
{
    public function fire(Job $job, $data)
    {
        $capterId = $data['capter_id'];

        // 获取翻译临时表中指定 capter_id 的所有记录
        $records = Db::name('capter_tran_temps')
            ->where('capter_id', $capterId)
            ->where('status', 1)
            ->select();

        if (empty($records)) {
            $job->delete();
            return;
        }

        $obj = new TranslateCapterInit();
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
                    'capter_id' => $capterId,
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
            foreach ($fields as $field) {
                if (empty($data[$field])) {
                    $isComplete = false;
                    break;
                }
            }

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
                $comicTran = ChapterTran::where([
                    'capter_id' => $data['capter_id'],
                    'lang_code' => $data['lang_code'],
                ])->find();

                if ($comicTran) {
                    // 更新已有记录
                    $comicTran->save($data);
                } else {
                    // 插入新记录
                    ChapterTran::create($data);
                }
            }

            // 清空已完成语言的旧翻译记录
            // Db::name('capter_trans')
            //     ->where('capter_id', $capterId)
            //     ->where('lang_code', 'in', $completedLangs)
            //     ->delete();

            // // 批量写入已完成语言的翻译数据
            // Db::name('capter_trans')->insertAll($insertList);

            // 清除已完成语言的临时翻译记录
            Db::name('capter_tran_temps')
                ->where('capter_id', $capterId)
                ->where('lang_code', 'in', $completedLangs)
                ->delete();

            // 检查是否所有语言的翻译都已完成
            $remaining = Db::name('capter_tran_temps')
                ->where('capter_id', $capterId)
                ->count();

            if ($remaining == 0) {
                // 所有语言翻译完成，更新主表状态
                Db::name('capter')->where('id', $capterId)->update([
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
        $remaining = Db::name('capter_tran_temps')
            ->where('capter_id', $capterId)
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
