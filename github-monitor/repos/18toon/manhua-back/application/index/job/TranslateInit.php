<?php

namespace app\index\job;

use app\index\model\Tags;
use app\index\model\Ticai;
use think\queue\Job;
use think\Db;
use think\Queue;

// Queue::push('app\index\job\TranslateInit', ['type' => 'ticai', 'type_id' => '1', 'fields' => ['name'], 'languages' => ['en']]);
// php think queue:listen

class TranslateInit
{
    public $languages = [
        'ru' => 'Russian',
        'vi' => 'Vietnamese',
        'hi' => 'Hindi',
        'en' => 'English',
        'es' => 'Spanish',
    ];

    public function fire(Job $job, $data)
    {
        if ($job->attempts() > 3) {
            $job->delete();
            return;
        }

        $type = $data['type'] ?? null;
        $typeId = $data['type_id'] ?? null;
        $fields = $data['fields'] ?? null;

        if (!$type || !$typeId || empty($fields)) {
            $job->delete();
            return;
        }

        if ($type === 'ticai') {
            $record = Ticai::find($typeId);
        } elseif ($type === 'tag') {
            $record = Tags::find($typeId);
        } else {
            $job->delete();
            return;
        }

        if (!$record) {
            $job->delete();
            return;
        }

        $selectedLangCodes = $data['languages'] ?? ['en'];
        $languages = array_intersect_key($this->languages, array_flip($selectedLangCodes));

        foreach ($languages as $langCode => $language) {
            foreach ($fields as $field) {
                $exists = Db::table('translation_tasks')->where([
                    'type' => $type,
                    'type_id' => $typeId,
                    'lang_code' => $langCode,
                    'field' => $field,
                ])->find();

                $originText = $record[$field] ?? '';

                if (!$originText) continue;

                if (!$exists) {
                    $taskId = Db::table('translation_tasks')->insertGetId([
                        'type' => $type,
                        'type_id' => $typeId,
                        'lang_code' => $langCode,
                        'field' => $field,
                        'origin_text' => $originText,
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                } else {
                    Db::table('translation_tasks')->where('id', $exists['id'])->update([
                        'origin_text' => $originText,
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                    $taskId = $exists['id'];
                }

                Queue::push('app\index\job\TranslateFieldJob', [
                    'task_id' => $taskId
                ]);
            }
        }

        $job->delete();
    }

    public function failed($data)
    {
        save_log('TranslateInit: ' . json_encode($data), "translate");
    }
}
