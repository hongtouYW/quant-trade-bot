<?php

namespace app\index\job;

use think\queue\Job;
use think\Db;
use think\Queue;

// 用法示例：Queue::push('app\index\job\TranslateManhuaActorInit', ['manhua_id' => 1]);

class TranslateManhuaActorInit
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

        $manhuaId = $data['manhua_id'] ?? null;
        if (!$manhuaId) {
            $job->delete();
            return;
        }

        $actors = Db::name('manhua_actors')->where('manhua_id', $manhuaId)->select();
        if (empty($actors)) {
            $job->delete();
            return;
        }

        $selectedLangCodes = $data['languages'] ?? ['en'];
        $languages = array_intersect_key($this->languages, array_flip($selectedLangCodes));

        foreach ($actors as $actor) {
            foreach ($languages as $langCode => $language) {
                $exists = Db::name('manhua_actor_tran_temps')->where([
                    'manhua_actor_id' => $actor['id'],
                    'lang_code' => $langCode,
                ])->find();

                if (!$exists) {
                    Db::name('manhua_actor_tran_temps')->insert([
                        'manhua_actor_id' => $actor['id'],
                        'lang_code' => $langCode,
                        'origin_text' => $actor['name'],
                        'translated_text' => '',
                        'status' => 0,
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                } else {
                    Db::name('manhua_actor_tran_temps')->where('id', $exists['id'])->update([
                        'origin_text' => $actor['name'],
                        'translated_text' => '',
                        'status' => 0,
                        'error_message' => '',
                        'update_time' => time(),
                    ]);
                }

                Queue::push('app\index\job\TranslateManhuaActorFieldJob', [
                    'manhua_actor_id' => $actor['id'],
                    'lang_code' => $langCode
                ]);
            }
        }

        $job->delete();
    }

    public function failed($data)
    {
        save_log('TranslateManhuaActorInit失败，数据: ' . json_encode($data), "translate");
    }
}
