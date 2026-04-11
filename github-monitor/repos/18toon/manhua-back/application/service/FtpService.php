<?php

namespace app\service;

class FtpService
{
    protected $ftpHost = '172.247.52.238';
    protected $ftpPort = 21;
    protected $ftpUser = 'mh_upload';
    protected $ftpPass = 'xhAx2sf';
    protected $ftpBaseDir = '/uploads/comics/';

    public function upload($localPath, $subDir = '')
    {
        $conn = ftp_connect($this->ftpHost, $this->ftpPort);
        if (!$conn) return false;

        $login = ftp_login($conn, $this->ftpUser, $this->ftpPass);
        ftp_pasv($conn, true);
        if (!$login) {
            ftp_close($conn);
            return false;
        }

        $filename = uniqid('img_') . '.' . pathinfo($localPath, PATHINFO_EXTENSION);
        $remoteDir = $this->ftpBaseDir . trim($subDir, '/');
        $remotePath = $remoteDir . '/' . $filename;

        $this->makeDirs($conn, $remoteDir);

        $upload = ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
        ftp_close($conn);

        if ($upload) {
            @unlink($localPath); // 删除本地 temp 文件
            return $remotePath;  // 返回远程路径
        }

        return false;
    }

    protected function makeDirs($conn, $path)
    {
        $parts = explode('/', trim($path, '/'));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            if (!@ftp_chdir($conn, $current)) {
                ftp_mkdir($conn, $current);
            }
        }
    }
}
