<?php
namespace app\traits;

use think\facade\Log;

trait HandlesImageUpload
{
    protected $savePath;
    protected $rsyncModule;
    protected $rsyncHost;
    protected $rsyncUser;
    protected $rsyncPassFile;

    /**
     * 初始化配置
     */
    protected function initRsyncConfig()
    {
        $this->savePath = rtrim(config('import.save_path'), '/') . '/';
        $this->rsyncHost = config('import.rsync_host');
        $this->rsyncModule = config('import.rsync_module');
        $this->rsyncUser = config('import.rsync_user');
        $this->rsyncPassFile = config('import.rsync_pass_file');
    }

    /**
     * 上传图片逻辑：下载并保存到本地（等待 Cron Job 异步同步）
     */
    protected function saveImageFromUrl($url, $remoteDir, $prefix = null)
    {
        $this->initRsyncConfig();

        if (empty($url)) {
            Log::warning("[LOCAL_SAVE] 图片 URL 为空");
            return null;
        }

        // 提取扩展名
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext) $ext = 'jpg';
        $prefixWithoutExt = pathinfo($prefix ?? '', PATHINFO_FILENAME);
        
        // 路径设置
        $subDir = trim($remoteDir, '/');
        $filename = $prefixWithoutExt ? $prefixWithoutExt . '.' . $ext : uniqid() . '.' . $ext;
        $localFullDir = $this->savePath . $subDir . '/';
        $localFile = $localFullDir . $filename;
        $remotePath = 'mh/' . $subDir . '/' . $filename;

        // 下载尝试配置
        $maxRetries = 3;
        $timeout = 10; // ⏱️ 超时时间（秒）
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                if (!is_dir($localFullDir)) {
                    mkdir($localFullDir, 0755, true);
                }

                // === 1️⃣ 获取远程文件头部信息（带超时） ===
                $headers = @get_headers($url, 1);
                $expectedSize = 0;
                if ($headers && isset($headers['Content-Length'])) {
                    $expectedSize = (int) $headers['Content-Length'];
                }

                // === 2️⃣ 下载文件，带超时控制 ===
                $context = stream_context_create([
                    'http' => [
                        'timeout' => $timeout,
                        'follow_location' => 1,
                        'ignore_errors' => true,
                        'header' => "User-Agent: PHP\r\n",
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);

                $imgData = @file_get_contents($url, false, $context);
                if ($imgData === false || strlen($imgData) === 0) {
                    throw new \Exception("下载失败或超时 (URL: $url)");
                }

                // 保存文件
                file_put_contents($localFile, $imgData);
                
                // ---------------------------------------------------------------
                // ⚠️ 原 rsync 逻辑 (已注释，仅保留供参考)
                /*
                $cmd = sprintf(
                    // 使用标准 rsync，并带上 password-file
                    'rsync -av --progress --password-file=%s %s %s@%s::%s/%s',
                    escapeshellarg($this->rsyncPassFile),
                    escapeshellarg($localFile),
                    escapeshellarg($this->rsyncUser),
                    escapeshellarg($this->rsyncHost),
                    escapeshellarg($this->rsyncModule),
                    escapeshellarg(trim($remoteDir, '/'))
                );

                exec($cmd, $output, $code);
                if ($code !== 0)
                    throw new \Exception("rsync 上传失败, 返回码 $code");
                
                // rsync 成功后清理本地文件 (如果 savePath 是临时目录)
                // @unlink($localFile); 
                */

                // === 3️⃣ 校验文件大小 ===
                $actualSize = filesize($localFile);
                if ($expectedSize > 0 && abs($expectedSize - $actualSize) > 1024) {
                    throw new \Exception("文件大小不匹配 (远程=$expectedSize, 本地=$actualSize)");
                }

                Log::info("[LOCAL_SAVE] 下载成功: $url => $remotePath (大小: $actualSize 字节)");
                return $remotePath;

            } catch (\Throwable $e) {
                $attempt++;
                Log::error("[LOCAL_SAVE] 尝试 {$attempt}/{$maxRetries} 失败: {$e->getMessage()}");

                // 删除损坏文件
                if (file_exists($localFile)) {
                    @unlink($localFile);
                }

                if ($attempt >= $maxRetries) {
                    Log::error("[LOCAL_SAVE] 多次重试后仍失败，放弃下载: $url");
                    return null;
                }

                sleep(1); // 等待1秒再试
            }
        }

        return null;
    }
}
