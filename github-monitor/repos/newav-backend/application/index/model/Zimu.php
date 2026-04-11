<?php
namespace app\index\model;

use think\Model;
use think\facade\Log;
use think\Db;

class Zimu extends Model
{
    const API_URL        = "http://69.30.85.239:22198/api/v1/jobs"; //translate
    // const API_URL        = "http://203.57.40.105:10183/api/v1/jobs"; //translate
    const CALLBACK_URL   = "https://newavadmin.9xyrp3kg4b86.com/zimu/zimu_callback";
    const LANG           = ["en", "zh", "ru", "th", "ms", "es", "vi"];
    const LANG_MAP       = [
        "en" => "English",
        "zh" => "Simplified Chinese",
        "ru" => "Russian",
        "th" => "Thai",
        "ms" => "Malay",
        "es" => "Spanish",
        "vi" => "Vietnamese",
    ];
    // const T_API_URL      = "http://95.216.229.253:8080/api/v1/jobs";
    const T_API_URL      = "http://69.30.85.239:22196/api/v1/jobs";
    // const T_API_URL      = "http://203.57.40.105:10180/api/v1/jobs";
    const T_CALLBACK_URL = "https://newavadmin.9xyrp3kg4b86.com/zimu/transcribe_callback";

    public static function sendToThirdParty($video)
    {
        try {
            if (is_numeric($video)) {
                $video = \app\index\model\Video::find($video);
            }
            if (!$video) {
                throw new \Exception("Video not found.");
            }
            if (empty($video['zimu'])) {
                throw new \Exception("Video has no zimu file path.");
            }

            // Build the full original transcript URL
            $zimuUrl = self::getZimuUrl($video);

            foreach (self::LANG as $lang) {
                // Build payload per API spec
                $payload = [
                    'externalID'     => "{$video->id}:{$lang}",
                    'vttURL'         => $zimuUrl,
                    'targetLanguage' => self::LANG_MAP[$lang] ?? $lang,
                    'callbackURL'    => self::CALLBACK_URL,
                ];

                save_log(json_encode($payload), 'zimu_translate');
                $ch = curl_init(self::API_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                save_log("response", 'zimu_translate');
                save_log(json_encode($response), 'zimu_translate');
                save_log($httpCode, 'zimu_translate');
                save_log(self::API_URL, 'zimu_translate');
                save_log(json_encode($payload), 'zimu_translate');
                
                if ($httpCode !== 202) {
                    Log::error("Zimu job failed: video={$video->id}, lang={$lang}, response={$response}");
                    Db::name('video')->where('id', $video->id)->update(['zimu_status' => 9]);
                    Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 2, 'event' => 3, 'lang' => $lang, 'created_at' => time()]);
                    return false;
                }
                Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 2, 'event' => 1, 'lang' => $lang, 'created_at' => time()]);
                // if ($httpCode === 201) {
                //     // Successfully created translation job
                //     Db::name('video')->where('id', $video->id)
                //         ->update(['zimu_status' => 1, 'update_time' => time()]);
                //     Log::info("Zimu job submitted, video ID: {$video->id}, response: {$response}");
                //     return true;
                // } else {
                //     Log::error("Zimu job submission failed, video ID: {$video->id}, HTTP {$httpCode}, response: {$response}");
                //     Db::name('video')->where('id', $video->id)->update(['zimu_status' => 3]);
                //     return false;
                // }
                usleep(300000); // 0.3s
            }
            Db::name('video')->where('id', $video->id)->update(['zimu_status' => 3, 'update_time' => time()]);
            return true;
        } catch (\Throwable $e) {
            Log::error("Zimu::sendToThirdParty error — " . $e->getMessage());
            if (!empty($video->id)) {
                Db::name('video')->where('id', $video->id)->update(['zimu_status' => 9]);
            }
            return false;
        }
    }

    private static function getZimuUrl($video)
    {
        $zimu = $video['zimu'];

        // If already a full URL (from transcription API), use directly
        if (preg_match('#^https?://#', $zimu)) {
            return $zimu;
        }

        $zimuUrl = Configs::get('zimu_url');
        $url     = $zimuUrl . '/' . ltrim($zimu, '/');

        // Insert video folder name if pattern matches
        if (preg_match('#/(subtitles)/#', $url) && preg_match('#/([a-z0-9]+__\d+)/subtitles/#i', $url, $matches)) {
            $videoId = $matches[1];
            $url = preg_replace('#/subtitles/#', '/subtitles/' . $videoId . '/', $url);
        }
        return $url;
    }

    // 提取字幕
    public static function sendTranscribeJob($id)
    {
        try {
            $video  = Video::find($id);
            $rawUrl = strtok($video->video_url ?? '', '?');
            if (empty($rawUrl)) {
                throw new \Exception('Missing Video URL');
            }

            // Use public-facing URL so the transcription server can access it
            $base   = rtrim(env('app.transcribe_src_url', env('app.video_dl_url', 'http://23.225.92.2:16562')), '/');
            $path   = parse_url($rawUrl, PHP_URL_PATH) ?: $rawUrl;
            $source = $rawUrl; // fallback
            if (preg_match('#/ms/amnew/([^/]+)/#', $path, $m)) {
                $folder = $m[1];
                $source = $base . '/ms/amnew/' . $folder . '/' . $folder . '.mp4';
            }

            $payload = [
                'external_id'  => 'video_id_' . $video->id,
                'source_url'   => $source,
                'callback_url' => self::T_CALLBACK_URL,
            ];

            save_log(json_encode($payload, JSON_UNESCAPED_UNICODE), 'zimu_transcribe');

            $ch = curl_init(self::T_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            save_log($response, 'zimu_transcribe');

            if ($httpCode !== 201) {
                Log::error("Transcribe enqueue failed: {$response}");
                Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 1, 'event' => 3, 'lang' => '', 'created_at' => time()]);
                return false;
            }

            // mark as processing
            Db::name('video')->where('id', $video->id)->update([
                'zimu_status' => 1,
                'update_time' => time()
            ]);
            Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 1, 'event' => 1, 'lang' => '', 'created_at' => time()]);
            return true;
        } catch (\Throwable $e) {
            Log::error('sendTranscribeJob error: ' . $e->getMessage());
            return false;
        }
    }

}
