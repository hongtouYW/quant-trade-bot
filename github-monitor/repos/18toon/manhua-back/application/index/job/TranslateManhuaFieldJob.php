<?php

namespace app\index\job;

use think\queue\Job;
use think\Db;
use GuzzleHttp\Client;
use think\Queue;

// php think queue:listen --queue tran_manhua_field

class TranslateManhuaFieldJob
{
    protected $apiUrl = 'http://69.30.85.237:22021/translate';
    protected $apiTimeout = 60;

    public function fire(Job $job, $data)
    {
        if ($job->attempts() > 3) {
            $this->failJob($data);
            $job->delete();
            return;
        }

        $manhuaId = $data['manhua_id'];
        $langCode = $data['lang_code'];
        $field = $data['field'];

        $record = Db::name('manhua_tran_temps')
            ->where([
                'manhua_id' => $manhuaId,
                'lang_code' => $langCode,
                'field' => $field
            ])
            ->find();

        $obj = new TranslateManhuaInit();
        $languages = $obj->languages;

        // 找不到语言名称则跳过
        if (!$record || !isset($languages[$langCode])) {
            Db::name('manhua_tran_temps')->where('id', $record['id'])->update([
                'status' => '2',
                'error_message' => "Unsupported or missing language code",
                'update_time' => time(),
            ]);
            $job->delete();
            return;
        }

        try {
            $originText = trim($record['origin_text']);

            if ($originText === '') {
                $translatedText = '';
            } else {
                $client = new Client(['timeout' => $this->apiTimeout]);

                $res = $client->post($this->apiUrl, [
                    'json' => [
                        'text' => $originText,
                        'target_language' => $languages[$langCode],
                    ]
                ]);

                $body = json_decode((string)$res->getBody(), true);
                $translatedText = $body['translated_text'] ?? '';
            }

            Db::name('manhua_tran_temps')->where('id', $record['id'])->update([
                'translated_text' => $translatedText,
                'status' => '1',
                'update_time' => time(),
            ]);

            // 检查是否全部翻译成功，推送同步 job
            $total = Db::name('manhua_tran_temps')->where('manhua_id', $manhuaId)->count();
            $success = Db::name('manhua_tran_temps')->where('manhua_id', $manhuaId)->where('status', 1)->count();

            if ($total == $success) {
                Queue::push('app\index\job\TranslatedManhuaJob', [
                    'manhua_id' => $manhuaId
                ], 'tran_manhua');
            }

            $job->delete();
        } catch (\Exception $e) {
            sendTelegramMessage("TranslateManhuaFieldJob: " . $e->getMessage(), 12);

            Db::name('manhua_tran_temps')->where('id', $record['id'])->update([
                'status' => 0, // 0 = pending/failed
                'error_message' => $e->getMessage(),
                'update_time' => time(),
            ]);
            $job->release(10);
        }
    }

    protected function failJob($data)
    {
        Db::name('manhua_tran_temps')->where([
            'manhua_id' => $data['manhua_id'],
            'lang_code' => $data['lang_code'],
            'field' => $data['field']
        ])->update([
            'status' => 2,
            'update_time' => time(),
        ]);
    }
}
