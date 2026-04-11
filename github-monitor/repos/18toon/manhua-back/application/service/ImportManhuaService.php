<?php
namespace app\service;

use think\Db;
use think\facade\Log;
use Overtrue\Pinyin\Pinyin;

class ImportManhuaService
{

    protected Pinyin $pinyin;

    public function __construct()
    {
        $this->pinyin = new Pinyin();
    }

    /**
     * 转换所有未转换漫画及章节
     */
    public function convertAll()
    {
        $convertedManhuaCount = 0;
        $convertedChapterCount = 0;

        // ===== 1️⃣ 转换漫画 =====
        $imports = Db::name('import_manhua')
            ->where('is_converted', 0)
            ->select();

        foreach ($imports as $import) {
            try {
                $newManhuaId = $this->convertManhua($import['id']);
                if ($newManhuaId)
                    $convertedManhuaCount++;
            } catch (\Exception $e) {
                Log::error("【漫画转换失败】importID: {$import['id']} - " . $e->getMessage());
            }
        }

        // ===== 2️⃣ 转换章节 =====
        $prefix = config('database.prefix');
        $tableCapterTrans = $prefix . 'import_capter_trans';

        $allImports = Db::name('import_manhua')
            ->where('is_converted', 1)
            ->select();

        foreach ($allImports as $import) {
            $newManhuaId = $import['converted_id'] ?? 0;
            if (!$newManhuaId)
                continue;

            $chapters = Db::name('import_capter')
                ->alias('c')
                ->leftJoin("{$tableCapterTrans} t", 't.capter_id = c.id')
                ->where('c.manhua_id', $import['id'])
                ->where(function ($query) {
                    $query->where('c.is_converted', 0)
                        ->whereOr('t.is_converted', 0);
                })
                ->group('c.id')
                ->field('c.id')
                ->select();

            foreach ($chapters as $chapter) {
                try {
                    if ($this->convertChapter($chapter['id'], $newManhuaId)) {
                        $convertedChapterCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("【章节转换失败】chapterID: {$chapter['id']} - " . $e->getMessage());
                }
            }
            $this->updateLastChapter($newManhuaId);
        }

        Log::info("转换完成：漫画 {$convertedManhuaCount} 个，章节 {$convertedChapterCount} 个");
    }

    /**
     * 转换单个漫画
     */
    public function convertManhua($id)
    {
        $import = Db::name('import_manhua')->where('id', $id)->find();
        if (!$import) {
            return 0;
        }

        // 检查主图是否远程（第一次转换才阻止）
        $imageFields = ['image', 'cover', 'cover_horizontal'];
        if (!$import['is_converted']) {
            foreach ($imageFields as $f) {
                if ($this->hasRemoteLink($import[$f])) {
                    Log::info("跳过漫画 {$id}：{$f} 仍为远程链接");
                    return 0;
                }
            }
        }

        // 如果已转换，使用已有 converted_id
        $newId = $import['converted_id'] ?? 0;

        // 如果还未转换漫画主记录，则创建
        if (!$newId) {
            $newId = Db::name('manhua')->insertGetId([
                'title' => $import['title'],
                'desc' => $import['desc'],
                'keyword' => $import['keyword'],
                'ticai_id' => $import['ticai_id'],
                'tags' => $import['tags'],
                'auther' => $import['author'],
                'image' => $import['image'],
                'cover' => $import['cover'],
                'cover_horizontal' => $import['cover_horizontal'],
                'mhstatus' => $import['mhstatus'],
                'status' => $import['status'],
                'age18' => $import['age18'],
                'translated_languages' => json_encode([]),
                'translate_status' => 0,
                'cjid' => $import['external_id'],
                'cjstatus' => 1,
                'cjname' => $import['from_source'],
                'project_visibility' => $import['project_visibility'],
                'view' => rand(1000, 5000),
                'create_time' => time(),
                'update_time' => time(),
            ]);

            if (!$newId)
                return 0;

            // 更新 import 主表
            Db::name('import_manhua')->where('id', $id)->update([
                'converted_id' => $newId,
                'is_converted' => 1
            ]);
        }

        // 转换未完成的翻译
        $successLangs = [];
        $transList = Db::name('import_manhua_trans')
            ->where('manhua_id', $id)
            ->where('is_converted', 0)
            ->select();

        foreach ($transList as $t) {
            $transImageFields = ['image', 'cover', 'cover_horizontal'];
            $skip = false;
            foreach ($transImageFields as $f) {
                if ($this->hasRemoteLink($t[$f])) {
                    Log::info("跳过漫画翻译 {$t['id']}：{$f} 为远程");
                    $skip = true;
                    break;
                }
            }
            if ($skip)
                continue;

            // 插入翻译
            Db::name('manhua_trans')->insert([
                'manhua_id' => $newId,
                'lang_code' => $t['lang_code'],
                'title' => $t['title'],
                'desc' => $t['desc'],
                'keyword' => $t['keyword'],
                'cover' => $t['cover'],
                'cover_horizontal' => $t['cover_horizontal'],
                'image' => $t['image'],
            ]);

            // 标记成功语言
            $successLangs[] = $t['lang_code'];

            // 更新import_manhua_trans
            Db::name('import_manhua_trans')->where('id', $t['id'])->update(['is_converted' => 1]);
        }

        // 更新 manhua 表翻译语言
        if (!empty($successLangs)) {
            Db::name('manhua')->where('id', $newId)->update([
                'translated_languages' => json_encode($successLangs),
                'translate_status' => 1,
            ]);
        }

        return $newId;
    }

    /**
     * 转换章节及其多语言翻译
     */
    public function convertChapter($chapterId, $manhuaId)
    {
        $chapter = Db::name('import_capter')->where('id', $chapterId)->find();
        if (!$chapter)
            return 0;

        $newChapterId = $chapter['converted_id'] ?? 0;

        // ======= 1. 主体未转换才插入章节 =======
        if (!$chapter['is_converted']) {

            if ($chapter['image'] && $this->hasRemoteLink($chapter['image'])) {
                Log::info("跳过章节 {$chapterId}: image 远程链接");
                return 0;
            }

            if ($chapter['imagelist'] && $this->hasRemoteLink($chapter['imagelist'])) {
                Log::info("跳过章节 {$chapterId}: imagelist 远程链接");
                return 0;
            }

            $importManhua = Db::name('import_manhua')
                ->where('id', $chapter['manhua_id'])
                ->find();

            $cjname = $importManhua['from_source'] ?? 'import';

            $newChapterId = Db::name('capter')->insertGetId([
                'manhua_id' => $manhuaId,
                'title' => $chapter['title'],
                'sort' => $chapter['sort'] ?? 0,
                'imagelist' => $this->formatImagelist($chapter['imagelist']),
                'switch' => 1,
                'image' => $chapter['image'],
                'isvip' => $chapter['isvip'],
                'score' => $chapter['score'],
                'view' => rand(100, 500),
                'cjid' => $chapter['external_id'],
                'cjname' => $cjname,
                'translate_status' => 0,
                'translated_languages' => json_encode([]),
                'create_time' => time(),
                'update_time' => time(),
            ]);

            // 更新 import_capter 主表
            Db::name('import_capter')->where('id', $chapterId)->update([
                'is_converted' => 1,
                'converted_id' => $newChapterId
            ]);
        }

        // ======= 2. 扫描未转换的翻译 =======
        $successLangs = [];
        $transList = Db::name('import_capter_trans')
            ->where('capter_id', $chapterId)
            ->where('is_converted', 0)
            ->select();
        foreach ($transList as $tran) {
            if ($this->hasRemoteLink($tran['imagelist'])) {
                Log::info("跳过章节翻译 {$tran['id']}：远程图");
                continue;
            }

            Db::name('capter_trans')->insert([
                'capter_id' => $newChapterId,
                'lang_code' => $tran['lang_code'],
                'title' => $tran['title'],
                'imagelist' => $this->formatImagelist($tran['imagelist']),
            ]);

            $successLangs[] = $tran['lang_code'];

            Db::name('import_capter_trans')->where('id', $tran['id'])->update([
                'is_converted' => 1
            ]);
        }

        // ======= 3. 更新章节主表翻译状态 =======
        if (!empty($successLangs)) {
            Db::name('capter')->where('id', $newChapterId)->update([
                'translate_status' => 1,
                'translate_img' => 2,
                'translated_languages' => json_encode($successLangs)
            ]);
        }

        return $newChapterId;
    }

    /**
     * 更新漫画的最新章节信息
     * @param int $manhuaId 转换后的新漫画ID
     * @return bool
     */
    public function updateLastChapter(int $manhuaId): bool
    {
        // 获取最新转换后的正式章节
        $lastChapter = Db::name('capter')
            ->where('manhua_id', $manhuaId)
            ->order('sort DESC,id DESC')
            ->find();

        if (!$lastChapter) {
            Log::warning("【更新最新章节失败】manhuaId={$manhuaId} 无章节记录");
            return false;
        }

        $chapterId = $lastChapter['id'];
        $chapterTitle = $lastChapter['title'] ?? '';

        // 更新 manhua 主表
        Db::name('manhua')->where('id', $manhuaId)->update([
            'last_chapter' => $chapterId,
            'last_chapter_title' => $chapterTitle,
            'update_time' => time(),
        ]);

        // 查询正式语言
        $langs = Db::name('manhua_trans')
            ->where('manhua_id', $manhuaId)
            ->column('lang_code');

        foreach ($langs as $langCode) {

            // 对应语言章节翻译（无则 fallback）
            $titleTrans = Db::name('capter_trans')
                ->where([
                    'capter_id' => $chapterId,
                    'lang_code' => $langCode
                ])
                ->value('title');

            $finalTitle = $titleTrans ?: $chapterTitle;

            Db::name('manhua_trans')
                ->where([
                    'manhua_id' => $manhuaId,
                    'lang_code' => $langCode
                ])
                ->update(['last_chapter_title' => $finalTitle]);

            Log::info("【更新最新章节→语言:$langCode 】manhuaId={$manhuaId} => {$finalTitle}");
        }

        Log::info("【更新成功】manhuaId={$manhuaId} => {$chapterTitle}");
        return true;
    }

    /**
     * 判断是否远程链接
     */
    private function hasRemoteLink($data)
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        return !empty($data) && (strpos($data, 'http://') !== false || strpos($data, 'https://') !== false);
    }
    /**
     * 格式化 imagelist
     */
    private function formatImagelist($imagelist)
    {
        if (empty($imagelist))
            return '';
        if (is_array($imagelist))
            return implode(',', $imagelist);

        $arr = json_decode($imagelist, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
            return implode(',', $arr);
        }
        return $imagelist;
    }

}
