<?php

namespace app\index\job;

use app\index\model\Tags;
use app\index\model\Ticai;
use think\queue\Job;
use think\Db;
use app\service\TranslateService;

class TranslateFieldJob
{
    public function fire(Job $job, $data)
    {
        // 最多重试 3 次
        if ($job->attempts() > 3) {
            $this->markFailed($data['task_id'], '超过最大重试次数');
            $job->delete();
            return;
        }

        $taskId = $data['task_id'] ?? null;

        if (!$taskId) {
            $job->delete();
            return;
        }

        // 获取翻译任务
        $task = Db::table('translation_tasks')->where('id', $taskId)->find();
        if (!$task || $task['status'] == 1) {
            $job->delete();
            return;
        }

        $type = $task['type'];
        $typeId = $task['type_id'];
        $text = $task['origin_text'];
        $field     = $task['field'];
        $toLang = $task['lang_code'];

        try {
            $service = new TranslateService();
            $translatedText = $service->translate($text, $toLang);

            if ($type === 'ticai') {
                $record = Ticai::find($typeId);
                $transTable = 'qiswl_ticai_trans';
                $foreignKey = 'ticai_id';
            } elseif ($type === 'tag') {
                $record = Tags::find($typeId);
                $transTable = 'tag_trans';
                $foreignKey = 'tag_id';
            } else {
                throw new \Exception("未知类型: $type");
            }

            if (!$record) {
                throw new \Exception("主记录不存在: $type [$typeId]");
            }

            $exist = Db::table($transTable)->where([
                $foreignKey => $typeId,
                'lang_code' => $toLang,
            ])->find();

            if ($exist) {
                Db::table($transTable)->where('id', $exist['id'])->update([
                    $field => $translatedText,
                ]);
            } else {
                Db::table($transTable)->insert([
                    $foreignKey => $typeId,
                    'lang_code' => $toLang,
                    $field => $translatedText,
                ]);
            }

            Db::table('translation_tasks')->where('id', $taskId)->update([
                'translated_text' => $translatedText,
                'status' => 1,
                'error_message' => '',
                'update_time' => time(),
            ]);

            Db::table('translation_tasks')->where('id', $taskId)->delete();

            $record->save(['translate_status' => 1]);
        } catch (\Throwable $e) {
            $this->markFailed($taskId, $e->getMessage());
            $job->release(10); // 延迟重试
        }
    }

    public function failed($data)
    {
        if (!empty($data['task_id'])) {
            $this->markFailed($data['task_id'], 'Job failed permanently');
        }
    }

    protected function markFailed($taskId, $message)
    {
        Db::table('translation_tasks')->where('id', $taskId)->update([
            'status' => -1,
            'error_message' => $message,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }
}
