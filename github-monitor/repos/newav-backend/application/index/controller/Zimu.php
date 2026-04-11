<?php

namespace app\index\controller;

use think\Controller;
use app\index\model\Video;
use think\facade\Log;
use think\Db;
use app\index\model\Zimu as ZimuModel;

class Zimu extends Controller
{
    public function zimu_callback()
    {
        $data = request()->post();
        save_log(json_encode($data, JSON_UNESCAPED_UNICODE), 'zimu_translate_callback');

        $externalId = $data['externalID'] ?? null;
        if (!$externalId) {
            return json(['code' => 400, 'msg' => 'Missing externalID']);
        }

        [$videoId, $lang] = explode(':', $externalId, 2);
        if (!$videoId || !$lang) {
            return json(['code' => 400, 'msg' => 'Invalid externalID format']);
        }

        $video = Video::find($videoId);
        if (!$video) {
            return json(['code' => 404, 'msg' => 'Video not found']);
        }

        // Only handle completed jobs
        if (($data['status'] ?? '') !== 'completed') {
            Log::error("zimu_translate_callback failed: video={$videoId}, lang={$lang}, reason=" . ($data['failureReason'] ?? ''));
            $video->zimu_status = 9;
            $video->save();
            return response('IGNORED', 200);
        }

        $translatedVttUrl = $data['translatedVTTURL'] ?? '';
        if (empty($translatedVttUrl)) {
            $video->zimu_status = 9;
            $video->save();
            return response('NO_VTT_URL', 200);
        }

        // Download VTT content from the URL
        $vttContent = @file_get_contents($translatedVttUrl);
        if (empty($vttContent)) {
            Log::error("zimu_translate_callback: failed to download VTT from {$translatedVttUrl}");
            $video->zimu_status = 9;
            $video->save();
            return response('VTT_DOWNLOAD_FAILED', 200);
        }

        try {
            $this->generateAndRsyncZimu($video, $lang, $vttContent);
            Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 2, 'event' => 2, 'lang' => $lang, 'created_at' => time()]);
            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('zimu_translate_callback error: ' . $e->getMessage());
            $video->zimu_status = 9;
            $video->save();
            Db::name('zimu_log')->insert(['video_id' => $video->id, 'type' => 2, 'event' => 3, 'lang' => $lang, 'created_at' => time()]);
            return response('ERROR', 500);
        }
    }

    private function generateAndRsyncZimu(Video $video, string $lang, string $vttContent)
    {
        $subtitleBasePath = '/ms/amnew/video_id_' . $video->id;

        $tmpRoot   = rtrim(ROOT_PATH . 'runtime/zimu_tmp/', '/') . '/';
        $rsyncUser = config('zimu.rsync.user');
        $rsyncHost = config('zimu.rsync.host');
        $rsyncModule = config('zimu.rsync.module');
        $rsyncPass = config('zimu.rsync.password');

        if (!is_dir($tmpRoot)) {
            mkdir($tmpRoot, 0755, true);
        }

        // DB + public path
        $relativePath = "{$subtitleBasePath}/{$lang}/subtitles.vtt";

        // Write temp VTT into mirrored local dir
        $localDir = $tmpRoot . ltrim($subtitleBasePath, '/') . '/' . $lang . '/';
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }
        $tmpFile = $localDir . 'subtitles.vtt';
        file_put_contents($tmpFile, $vttContent);

        // Temp rsync password file
        $passFile = $tmpRoot . 'rsync_pass_' . uniqid();
        file_put_contents($passFile, $rsyncPass);
        chmod($passFile, 0600);

        // Rsync with --relative so remote dirs are auto-created
        $localWithMarker = $tmpRoot . './' . ltrim($subtitleBasePath, '/') . '/' . $lang . '/subtitles.vtt';
        $remoteBase      = sprintf('%s@%s::%s', $rsyncUser, $rsyncHost, $rsyncModule);
        $cmd = sprintf(
            'rsync -avrP --relative %s %s --password-file=%s 2>&1',
            escapeshellarg($localWithMarker),
            escapeshellarg($remoteBase),
            escapeshellarg($passFile)
        );

        Log::error('RSYNC CMD: ' . $cmd);
        $output = shell_exec($cmd);
        Log::error("RSYNC OUTPUT ({$lang}): " . $output);

        @unlink($tmpFile);
        @unlink($passFile);
        @rmdir($localDir);

        $column = "zimu_{$lang}";
        $video->$column = $relativePath;

        $allDone = true;
        foreach (\app\index\model\Zimu::LANG as $l) {
            $col = "zimu_{$l}";
            if (empty($video->$col)) {
                $allDone = false;
                break;
            }
        }
        if ($allDone) {
            $video->zimu_status = 4;
        }
        $video->save();
    }


    // ===============提取字幕===============
    
    public function transcribe() //no calling from the index page! put here just test from local
    {
        $videoId = input('param.id');
        $result  = ZimuModel::sendTranscribeJob($videoId);
        return $result
            ? json(['code' => 1, 'msg' => '字幕提取中'])
            : json(['code' => 0, 'msg' => '字幕提取失败']);
    }

    public function transcribe_callback()
    {
        $data     = request()->post();
        $basePath = "/ms/amnew";
        $videoId  = null;

        save_log(json_encode($data, JSON_UNESCAPED_UNICODE), 'transcribe_callback');
        $externalId = $data['external_id'] ?? '';
        if (!$externalId) {
            return response('MISSING_EXTERNAL_ID', 200);
        }

        if (preg_match('/video_id_(\d+)/', $externalId, $m)) {
            $videoId = (int)$m[1];
        }

        if (!$videoId) {
            return response('INVALID_EXTERNAL_ID', 200);
        }

        $video = Video::find($videoId);
        if (!$video) {
            return response('VIDEO_NOT_FOUND', 200);
        }

        if (($data['status'] ?? '') !== 'completed') {
            $video->zimu_status = 8; // 提取字幕失败
            $video->save();
            Db::name('zimu_log')->insert(['video_id' => $videoId, 'type' => 1, 'event' => 3, 'lang' => '', 'created_at' => time()]);
            return response('FAILED', 200);
        }

        $vttUrl = $data['vtt_url'] ?? '';
        if (!$vttUrl) {
            $video->zimu_status = 8;
            $video->save();
            return response('NO_VTT', 200);
        }

        // Download VTT content
        $vttContent = @file_get_contents($vttUrl);
        if (empty($vttContent)) {
            Log::error("transcribe_callback: failed to download VTT from {$vttUrl}");
            $video->zimu_status = 8;
            $video->save();
            return response('VTT_DOWNLOAD_FAILED', 200);
        }

        // Rsync to subtitle server: /ms/amnew/video_id_XXXXX/ja/subtitles.vtt
        $subtitleBase = "/ms/amnew/video_id_{$videoId}";
        $tmpRoot      = rtrim(ROOT_PATH . 'runtime/zimu_tmp/', '/') . '/';
        $rsyncUser    = config('zimu.rsync.user');
        $rsyncHost    = config('zimu.rsync.host');
        $rsyncModule  = config('zimu.rsync.module');
        $rsyncPass    = config('zimu.rsync.password');

        if (!is_dir($tmpRoot)) {
            mkdir($tmpRoot, 0755, true);
        }

        $localDir = $tmpRoot . ltrim($subtitleBase, '/') . '/ja/';
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }
        $tmpFile  = $localDir . 'subtitles.vtt';
        file_put_contents($tmpFile, $vttContent);

        $passFile = $tmpRoot . 'rsync_pass_' . uniqid();
        file_put_contents($passFile, $rsyncPass);
        chmod($passFile, 0600);

        $localWithMarker = $tmpRoot . './' . ltrim($subtitleBase, '/') . '/ja/subtitles.vtt';
        $remoteBase      = sprintf('%s@%s::%s', $rsyncUser, $rsyncHost, $rsyncModule);
        $cmd = sprintf(
            'rsync -avrP --relative %s %s --password-file=%s 2>&1',
            escapeshellarg($localWithMarker),
            escapeshellarg($remoteBase),
            escapeshellarg($passFile)
        );
        $output = shell_exec($cmd);
        Log::error("transcribe_callback rsync ({$videoId}): " . $output);

        @unlink($tmpFile);
        @unlink($passFile);
        @rmdir($localDir);

        $relativePath = "{$subtitleBase}/ja/subtitles.vtt";
        $video->zimu        = $relativePath;
        $video->zimu_status = 2; // 提取成功，等待翻译
        $video->save();

        Db::name('zimu_log')->insert(['video_id' => $videoId, 'type' => 1, 'event' => 2, 'lang' => '', 'created_at' => time()]);

        // ZimuModel::sendToThirdParty($video);

        return response('OK', 200);
    }

    // public function transcribe_callback22222()
    // {
    //     $data = request()->post();
    //     $path = "/ms/amnew";
    //     save_log(json_encode($data, JSON_UNESCAPED_UNICODE), 'transcribe_callback');

    //     $externalId = $data['external_id'] ?? null;
    //     [$type, $videoId] = explode(':', $externalId, 2);
    //     if (!$externalId) {
    //         return response('MISSING_EXTERNAL_ID', 200);
    //     }
        
    //     $video = Video::find($videoId);
    //     if (!$video) {
    //         return response('VIDEO_NOT_FOUND', 200);
    //     }

    //     // FAILED
    //     if (($data['status'] ?? '') !== 'completed') {
    //         $video->zimu_status = 8; // 提取字幕失败
    //         $video->save();
    //         return response('FAILED', 200);
    //     }

    //     // SUCCESS
    //     $vttPath = $data['vtt_url'] ?? '';
    //     if (!$vttPath) {
    //         $video->zimu_status = 8;
    //         $video->save();
    //         return response('NO_VTT', 200);
    //     }
    //     // save original subtitle path (or)
    //     $video->zimu = $path.$vttPath;
    //     $video->zimu_status = 2; // 提取成功，等待翻译
    //     $video->save();
    //     return response('OK', 200);
    // }
}
