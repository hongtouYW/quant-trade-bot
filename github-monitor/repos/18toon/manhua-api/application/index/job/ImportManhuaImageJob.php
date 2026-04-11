<?php
namespace app\index\job;

use think\queue\Job;
use think\Db;
use think\facade\Log;
use app\traits\HandlesImageUpload;

class ImportManhuaImageJob
{
    use HandlesImageUpload; 

    /**
     * @var string 默认数据库连接名
     */
    protected $defaultConnection = 'mysql';

    /**
     * 任务执行入口
     * @param Job $job 队列任务对象
     * @param array $data 任务数据
     */
    public function fire(Job $job, $data)
    {
        // 初始化环境配置
        $this->initRsyncConfig();

        Log::info('[Queue] ImportManhuaImageJob 启动: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $type = $data['type'] ?? '';
        $recordId = $data['id'] ?? 0;
        
        $connection = $data['connection'] ?? $this->defaultConnection; 

        if (!$type || !$recordId) {
            Log::error('任务参数错误');
            $job->delete();
            return;
        }

        try {
            switch ($type) {
                case 'manhua':
                    $this->handleManhua($recordId, $connection);
                    break;
                case 'chapter':
                    $this->handleChapter($recordId, $connection);
                    break;
                case 'actor':
                    $this->handleActor($recordId, $connection);
                    break;
                default:
                    Log::error("不支持的任务类型: {$type}");
            }

            $job->delete();
        } catch (\Throwable $e) {
            Log::error("[Queue] 导入图片任务失败，ID: {$recordId}, Type: {$type}：{$e->getMessage()}\nTrace: {$e->getTraceAsString()}");
            $job->release(30); // 30秒后重试
        }
    }

    /**
     * 获取数据库查询对象 (修正为直接在 Db 门面上指定连接)
     * @param string $connection 连接标识
     * @return \think\db\Query
     */
    private function getDbQuery(string $connection)
    {
        // 确保 $connection 为 'ct_db' 或 'mysql'，否则使用 null（默认连接）
        $connectionName = ($connection === 'ct_db' || $connection === 'mysql') ? $connection : null;
        
        // 返回一个用于链式操作的 Query 对象，并指定连接
        return Db::connect($connectionName);
    }
    
    /**
     * 统一处理记录中的图片字段下载与更新
     * @param string $connection 数据库连接标识
     * @param string $tableName 表名 (不含前缀)
     * @param int $recordId 记录ID
     * @param array $fields 需要处理的图片字段名数组
     * @param string $saveDir 图片保存目录
     * @param string $prefixPrefix 文件名前缀（用于区分不同类型记录）
     */
    private function handleRecordImageFields(string $connection, string $tableName, int $recordId, array $fields, string $saveDir, string $prefixPrefix): void
    {
        // 使用 Db::connect($connection)->name($tableName) 来执行查询
        $db = $this->getDbQuery($connection);
        
        $record = $db->name($tableName)->find($recordId);
        if (!$record) {
            return;
        }

        $updateData = [];
        foreach ($fields as $field) {
            $url = $record[$field] ?? '';
            if (!empty($url) && $this->isUrl($url)) {
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                // 构造文件名：前缀_ID_字段_时间戳.扩展名
                $filename = "{$prefixPrefix}_{$recordId}_{$field}_" . time() . '.' . $ext;

                $path = $this->saveImageFromUrl($url, $saveDir, $filename);
                if ($path) {
                    $updateData[$field] = $path;
                }
            }
        }

        if ($updateData) {
            // 使用 Db::connect($connection)->name($tableName) 来执行更新
            $db->name($tableName)->where('id', $recordId)->update($updateData);
            Log::info("{$tableName} ID:{$recordId} 图片字段更新成功: " . json_encode(array_keys($updateData)));
        }
    }

    /**
     * 统一处理章节 imagelist 字段 (下载图片数组并更新JSON)
     * 并在主表 import_capter 时额外处理单张 image 字段
     *
     * @param string $connection 数据库连接标识
     * @param string $tableName 表名 (不含前缀)
     * @param int $recordId 记录ID (章节或章节翻译)
     * @param string $saveDir 图片保存目录
     * @param string $prefixPrefix 文件名前缀（用于区分主表/翻译表）
     * @param string $langCode 语言代码 (仅翻译表需要)
     */
    private function handleChapterImageList(string $connection, string $tableName, int $recordId, string $saveDir, string $prefixPrefix, string $langCode = ''): void
    {
        $db = $this->getDbQuery($connection);
        $where = $langCode ? ['id' => $recordId, 'lang_code' => $langCode] : ['id' => $recordId];
        
        $record = $db->name($tableName)->where($where)->find($recordId);
        if (!$record) {
            return;
        }

        $logType = $langCode ? "章节翻译(Lang:{$langCode})" : "章节";
        $langPart = $langCode ? "_{$langCode}" : "";

        /**
         * ✅ 如果是 import_capter 表，额外处理单图 image 字段
         */
        if ($tableName === 'import_capter' && !empty($record['image']) && $this->isUrl($record['image'])) {
            $url = $record['image'];
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "{$prefixPrefix}_{$recordId}{$langPart}_image_" . time() . '.' . $ext;

            $path = $this->saveImageFromUrl($url, $saveDir, $filename);
            if ($path) {
                $db->name($tableName)->where('id', $recordId)->update(['image' => $path]);
                Log::info("{$logType} image 字段图片上传成功: {$url} -> {$path}");
            }
        }

        /**
         * ✅ 处理 imagelist 数组
         */
        if (empty($record['imagelist'])) {
            return;
        }

        $imagelist = json_decode($record['imagelist'], true);
        if (!$imagelist) {
            return;
        }

        $newPaths = [];
        foreach ($imagelist as $i => $url) {
            if ($this->isUrl($url)) {
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = "{$prefixPrefix}_{$recordId}{$langPart}_{$i}_" . time() . '.' . $ext;

                $path = $this->saveImageFromUrl($url, $saveDir, $filename);
                if ($path) {
                    $newPaths[] = $path;
                    Log::info("{$logType} imagelist 图片上传成功: {$url} -> {$path}");
                } else {
                    $newPaths[] = $url;
                }
            } else {
                $newPaths[] = $url;
            }
        }

        // 如果图片路径有变化才更新数据库
        if ($newPaths !== $imagelist) {
            $db->name($tableName)
                ->where($where)
                ->update(['imagelist' => json_encode($newPaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            Log::info("{$tableName} ID:{$recordId} {$logType} imagelist 更新成功.");
        }
    }


    /**
     * 处理漫画主表和翻译表图片的下载与更新
     * @param int $id 漫画ID
     * @param string $connection 数据库连接标识
     */
    private function handleManhua(int $id, string $connection): void
    {
        $fields = ['cover', 'cover_horizontal', 'image'];
        $saveDir = 'manhua';
        
        // 1. 处理主表图片 (传入连接名而非 Connection 实例)
        $this->handleRecordImageFields($connection, 'import_manhua', $id, $fields, $saveDir, 'manhua');

        // 2. 处理多语翻译表图片
        $db = $this->getDbQuery($connection);
        $transTable = 'import_manhua_trans';
        
        // 修正：使用 $db->name()
        $transList = $db->name($transTable)->where('manhua_id', $id)->select();
        
        foreach ($transList as $t) {
            $transId = $t['id'];
            $updateData = [];
            foreach ($fields as $field) {
                $url = $t[$field] ?? '';
                if (!empty($url) && $this->isUrl($url)) {
                    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    // 构造文件名：前缀_ID_语言_字段_时间戳.扩展名
                    $filename = "manhua_trans_{$id}_{$t['lang_code']}_{$field}_" . time() . '.' . $ext;

                    $path = $this->saveImageFromUrl($url, $saveDir, $filename);
                    if ($path) {
                        $updateData[$field] = $path;
                    }
                }
            }
            if ($updateData) {
                $db->name($transTable)->where('id', $transId)->update($updateData);
                Log::info("{$transTable} ID:{$transId} 图片字段更新成功: " . json_encode(array_keys($updateData)));
            }
        }
    }

    /**
     * 处理章节主表和翻译表图片的下载与更新
     * @param int $id 章节ID
     * @param string $connection 数据库连接标识
     */
    private function handleChapter(int $id, string $connection): void
    {
        $saveDir = 'chapter';
        
        // 1. 处理章节主表图片列表 (传入连接名而非 Connection 实例)
        $this->handleChapterImageList($connection, 'import_capter', $id, $saveDir, 'chapter');

        // 2. 处理章节翻译表图片列表
        $db = $this->getDbQuery($connection);
        $transTable = 'import_capter_trans';
        
        // 修正：使用 $db->name()
        $transList = $db->name($transTable)->where('capter_id', $id)->select();

        foreach ($transList as $t) {
            // 注意：handleChapterImageList 在处理翻译表时传入连接名
            $this->handleChapterImageList($connection, $transTable, $t['id'], $saveDir, 'chapter_trans', $t['lang_code']);
        }
    }

    /**
     * 处理演员图片的下载与更新
     * @param int $id 演员ID
     * @param string $connection 数据库连接标识
     */
    private function handleActor(int $id, string $connection): void
    {
        $fields = ['img'];
        $saveDir = 'actor';
        
        // 演员表只需处理单张图片字段 (传入连接名而非 Connection 实例)
        $this->handleRecordImageFields($connection, 'import_manhua_actors', $id, $fields, $saveDir, 'actor');
    }
    
    /**
     * 检查字符串是否为 URL
     * (保留原方法，假设它在 Trait 中不存在或为了兼容旧逻辑)
     * @param string $str
     * @return bool
     */
    private function isUrl(string $str): bool
    {
        return preg_match('/^https?:\/\//i', $str) === 1;
    }
}