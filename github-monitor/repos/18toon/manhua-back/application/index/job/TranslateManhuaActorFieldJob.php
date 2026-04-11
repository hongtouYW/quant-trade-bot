<?php

namespace app\index\job;

use app\service\TranslateService;
use think\queue\Job;
use think\Db;

class TranslateManhuaActorFieldJob
{
    public function fire(Job $job, $data)
    {
        $actorId = $data['manhua_actor_id'] ?? null;
        $langCode = $data['lang_code'] ?? null;

        if (!$actorId || !$langCode) {
            $job->delete();
            return;
        }

        $temp = Db::name('manhua_actor_tran_temps')->where([
            'manhua_actor_id' => $actorId,
            'lang_code' => $langCode,
            'status' => 0,
        ])->find();

        if (!$temp) {
            $job->delete();
            return;
        }

        try {
            // 假设你有一个 translate() 函数：translate($text, $targetLang)
            $service = new TranslateService();
            $translatedText = $service->translate($temp['origin_text'], $langCode);

            // 更新临时翻译表状态
            Db::name('manhua_actor_tran_temps')->where('id', $temp['id'])->update([
                'translated_text' => $translatedText,
                'status' => 1,
                'update_time' => time(),
            ]);

            // 同步到正式翻译表 manhua_actor_trans
            $exists = Db::name('manhua_actor_trans')->where([
                'manhua_actor_id' => $actorId,
                'lang_code' => $langCode,
            ])->find();

            if ($exists) {
                Db::name('manhua_actor_trans')->where('id', $exists['id'])->update([
                    'name' => $translatedText,
                ]);
            } else {
                Db::name('manhua_actor_trans')->insert([
                    'manhua_actor_id' => $actorId,
                    'lang_code' => $langCode,
                    'name' => $translatedText,
                ]);
            }

            // 删除成功的临时记录
            Db::name('manhua_actor_tran_temps')->where('id', $temp['id'])->delete();

            $manhuaId = Db::name('manhua_actors')->where('id', $actorId)->value('manhua_id');
            $pending = Db::name('manhua_actor_tran_temps')
                ->alias('t')
                ->join('manhua_actors a', 't.manhua_actor_id = a.id')
                ->where('a.manhua_id', $manhuaId)
                ->count();

            if ($pending === 0) {
                Db::name('manhua')->where('id', $manhuaId)->update([
                    'actor_translate' => '1',
                    'update_time' => time(),
                ]);
            }
        } catch (\Throwable $e) {
            // 翻译失败记录日志与状态
            Db::name('manhua_actor_tran_temps')->where('id', $temp['id'])->update([
                'status' => 2,
                'error_message' => $e->getMessage(),
                'update_time' => time(),
            ]);
        }

        $job->delete();
    }

    public function failed($data)
    {
        save_log('TranslateManhuaActorFieldJob 执行失败: ' . json_encode($data), 'translate');
    }
}
