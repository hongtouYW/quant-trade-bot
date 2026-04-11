<?php

namespace app\index\job;

use think\queue\Job;
use think\Db;
use think\Queue;

// Queue::push('app\index\job\TranslateCapterInit', ['capter_id' => '1'], 'tran_capter_init');
// php think queue:listen --queue=tran_capter_init

class TranslateCapterInit
{
    public $languages = [
        'ru' => 'Russian',
        'vi' => 'Vietnamese',
        'hi' => 'Hindi',
        'en' => 'English',
        'es' => 'Spanish',
    ];

    public $fields = ['title'];

    public function fire(Job $job, $data)
    {
        if ($job->attempts() > 3) {
            $job->delete();
            return;
        }

        $capterId = $data['capter_id'] ?? null;
        if (!$capterId) {
            $job->delete();
            return;
        }

        $capter = Db::name('capter')->where('id', $capterId)->find();
        if (!$capter) {
            $job->delete();
            return;
        }

        $selectedLangCodes = $data['languages'] ?? ['en'];
        $languages = array_intersect_key($this->languages, array_flip($selectedLangCodes));

        foreach ($languages as $langCode => $language) {
            foreach ($this->fields as $field) {
                $exists = Db::name('capter_tran_temps')->where([
                    'capter_id' => $capterId,
                    'lang_code' => $langCode,
                    'field' => $field,
                ])->find();

                if (!$exists) {
                    Db::name('capter_tran_temps')->insert([
                        'capter_id' => $capterId,
                        'lang_code' => $langCode,
                        'field' => $field,
                        'origin_text' => $capter[$field],
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                } else {
                    Db::name('capter_tran_temps')->where('id', $exists['id'])->update([
                        'origin_text' => $capter[$field],
                        'translated_text' => '',
                        'status' => '0',
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                }

                Queue::push('app\index\job\TranslateCapterFieldJob', [
                    'capter_id' => $capterId,
                    'lang_code' => $langCode,
                    'field' => $field
                ], 'tran_capter_field');
            }
        }

        Db::name('capter')->where('id', $capterId)->update([
            'update_time' => time(),
        ]);

        $job->delete();
    }

    public function failed($data)
    {
        save_log('TranslateCapterInit失败，数据: ' . json_encode($data), "translate");
    }
}
