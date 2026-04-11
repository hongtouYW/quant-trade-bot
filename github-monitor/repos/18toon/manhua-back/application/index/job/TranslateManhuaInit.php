<?php

namespace app\index\job;

use think\queue\Job;
use think\Db;
use think\Queue;

// Queue::push('app\index\job\TranslateManhuaInit', ['manhua_id' => '1'], 'tran_manhua_init');
// php think queue:listen --queue=tran_manhua_init

class TranslateManhuaInit
{
    public $languages = [
        'ru' => 'Russian',
        'vi' => 'Vietnamese',
        'hi' => 'Hindi',
        'en' => 'English',
        'es' => 'Spanish',
    ];

    public $fields = ['title', 'desc', 'keyword', 'last_chapter_title'];

    public function fire(Job $job, $data)
    {
        if ($job->attempts() > 3) {
            $job->delete();
            return;
        }

        $manhuaId = $data['manhua_id'] ?? null;
        if (!$manhuaId) {
            $job->delete();
            return;
        }

        $manhua = Db::name('manhua')->where('id', $manhuaId)->find();
        if (!$manhua) {
            $job->delete();
            return;
        }

        $selectedLangCodes = $data['languages'] ?? ['en'];
        $languages = array_intersect_key($this->languages, array_flip($selectedLangCodes));

        foreach ($languages as $langCode => $language) {
            foreach ($this->fields as $field) {
                $exists = Db::name('manhua_tran_temps')->where([
                    'manhua_id' => $manhuaId,
                    'lang_code' => $langCode,
                    'field' => $field,
                ])->find();

                if (!$exists) {
                    Db::name('manhua_tran_temps')->insert([
                        'manhua_id' => $manhuaId,
                        'lang_code' => $langCode,
                        'field' => $field,
                        'origin_text' => $manhua[$field],
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                } else {
                    Db::name('manhua_tran_temps')->where('id', $exists['id'])->update([
                        'origin_text' => $manhua[$field],
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                }

                Queue::push('app\index\job\TranslateManhuaFieldJob', [
                    'manhua_id' => $manhuaId,
                    'lang_code' => $langCode,
                    'field' => $field
                ], 'tran_manhua_field');
            }
        }

        Db::name('manhua')->where('id', $manhuaId)->update([
            'update_time' => time(),
        ]);

        $job->delete();
    }

    public function failed($data)
    {
        save_log('TranslateManhuaInit失败，数据: ' . json_encode($data), "translate");
    }
}
