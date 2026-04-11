<?php

namespace app\index\model;

use think\Model;
use think\facade\Log;

class Translate extends Model
{
    protected $table = 'translate';

    const CALLBACK_URL = "https://newavadmin.9xyrp3kg4b86.com/translate/translate_callback";

    /**
     * Submit translation jobs (ONE FIELD = ONE JOB)
     */
    public static function submit(string $modelType = null, int $id): bool
    {
        try {
            // 1️⃣ Resolve model
            switch ($modelType) {
                case 'publisher': $model = Publisher::find($id); break;
                case 'actor':     $model = Actor::find($id); break;
                case 'tag':       $model = Tags::find($id); break;
                case 'group':     $model = Group::find($id); break;
                case 'hotlist':   $model = Hotlist::find($id); break;
                case 'article':   $model = Articles::find($id); break;
                case 'blood':     $model = Blood::find($id); break;
                case 'video':
                default:          $model = Video::find($id); break;
            }

            if (!$model) {
                throw new \Exception("Model not found: {$modelType} {$id}");
            }

            // 2️⃣ Translate fields
            if (!property_exists($model, 'translateFields') || empty($model::$translateFields)) {
                throw new \Exception("translateFields not defined");
            }

            // 3️⃣ Target languages (lang_code ONLY)
            $targetLanguages = self::where('status', 1)->column('lang_code');
            if (empty($targetLanguages)) {
                throw new \Exception('No active target languages');
            }

            $targetLanguages = array_values(array_filter(array_map(
                fn($l) => strtolower(trim($l)),
                $targetLanguages
            )));

            // 4️⃣ Submit ONE JOB PER FIELD
            foreach ($model::$translateFields as $field) {

                $text = (string)$model->getAttr($field);
                if ($text === '') {
                    continue;
                }

                $payload = [
                    'service' => 'translation',
                    'data' => [
                        'input' => $text,
                        'source_language' => 'auto',
                        'target_languages' => $targetLanguages,
                        'temperature' => 0,
                        'format' => 'plain_text',
                    ],
                    'external_id' => "{$modelType}:{$id}:{$field}",
                    'callback_url' => self::CALLBACK_URL,
                    // 'callback_url' => url('translate/translate_callback', [], true, true),
                    'metadata' => [
                        'model' => $modelType,
                        'id'    => (string)$id,
                        'field' => $field,
                    ],
                ];

                Log::error("translate_submit :".json_encode($payload, JSON_UNESCAPED_UNICODE));
                // save_log(json_encode($payload, JSON_UNESCAPED_UNICODE), 'translate_submit');
                $ch = curl_init(config('zimu.translate.api_url'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . config('zimu.translate.api_key'),
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, config('zimu.translate.timeout', 30));

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                Log::error("translate_submit response :".json_encode($response, JSON_UNESCAPED_UNICODE));
                // save_log($response, 'translate_submit');

                if ($httpCode !== 201) {
                    Log::error("Translate failed {$modelType} {$id} {$field} | {$response}");
                }
            }

            return true;

        } catch (\Throwable $e) {
            Log::error("Translate submit exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle Translation callback
     */
    public static function handleCallback(array $data)
    {
        save_log(json_encode($data, JSON_UNESCAPED_UNICODE), 'translate_callback_title_des_actor');

        if (($data['status'] ?? '') !== 'completed') {
            return true;
        }

        $meta      = $data['data']['payloads']['metadata'] ?? $data['metadata'] ?? [];
        $modelType = $meta['model'] ?? '';
        $id        = $meta['id'] ?? null;
        $field     = $meta['field'] ?? '';

        if (!$modelType || !$id || !$field) {
            Log::error('INVALID META: ' . json_encode($meta));
            return false;
        }

        $modelClass = "\\app\\index\\model\\" . ucfirst($modelType);
        if (!class_exists($modelClass)) {
            Log::error("MODEL NOT FOUND: {$modelClass}");
            return false;
        }

        $model = $modelClass::find($id);
        if (!$model) {
            Log::error("ROW NOT FOUND: {$modelType} {$id}");
            return false;
        }

        // lang_code → suffix
        $langMap = self::where('status', 1)->column('key', 'lang_code');

        $outputs = $data['data']['outputs'] ?? [];

        foreach ($outputs as $lang => $arr) {

            if (!isset($langMap[$lang])) {
                continue;
            }

            // 🔥 IMPORTANT: output is ARRAY
            $text = is_array($arr) ? ($arr[0] ?? '') : $arr;
            if ($text === '') {
                continue;
            }

            $suffix = $langMap[$lang];
            if ($suffix[0] !== '_') {
                $suffix = '_' . $suffix;
            }

            $column = $field . $suffix;

            if (array_key_exists($column, $model->getData())) {
                $model->$column = $text;
            }
        }

        $model->save();
        return true;
    }

}
