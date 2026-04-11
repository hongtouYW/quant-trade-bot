<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;

use app\index\model\Chapter;
use app\index\model\OldChapter;
use app\index\model\OldComic as OldComicModel;
use app\index\model\Comic as ComicModel;
use app\index\model\Configs;
use app\index\model\OldTicai as TicaiModel;  //标签
use app\index\model\OldTags as TagsModel;  //标签
use think\cache\driver\Redis;
use think\Db;
use think\Queue;

class Oldcomic extends Base
{
    protected $model = '';
    protected $chapterModel = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new OldComicModel;
        $this->chapterModel = new OldChapter();
        $ticaiList = TicaiModel::field('id,name')->where('switch', '=', 1)->order('id asc')->select();
        $this->assign('ticaiList', $ticaiList);
        $tagList = TagsModel::field('id,name')->where('status', '=', 1)->order('sort desc')->select();
        $this->assign('tagList', $tagList);
    }

    public function index()
    {

        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;
        $where = [];
        if (!empty($param['id'])) {
            $param['id'] = trim($param['id']);
            $where[] = ['m.id', '=', $param['id']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where[] = ['m.title', 'like', '%' . $param['wd'] . '%'];
        }
        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['m.status', 'eq', $param['status']];
        }
        if (in_array($param['issole'], ['0', '1'], true)) {
            $where[] = ['m.issole', 'eq', $param['issole']];
        }
        if (in_array($param['isjingpin'], ['0', '1'], true)) {
            $where[] = ['m.isjingpin', 'eq', $param['isjingpin']];
        }
        if (in_array($param['xianmian'], ['0', '1'], true)) {
            $where[] = ['m.xianmian', 'eq', $param['xianmian']];
        }
        if (in_array($param['mhstatus'], ['0', '1'], true)) {
            $where[] = ['m.mhstatus', 'eq', $param['mhstatus']];
        }
        if (!empty($param['ticai_id'])) {
            $param['ticai_id'] = trim($param['ticai_id']);
            $where[] = ['m.ticai_id', '=', $param['ticai_id']];
        }
        if (in_array($param['migrate'], ['0', '1', '2'], true)) {
            $where['migrate'] = $param['migrate'];
        }
        $order = 'm.update_time desc';
        $res = $this->model->listData($where, $order, $param['page'], $param['limit']);
        $this->assign([
            'list' => $res['list'],
            'total' => $res['total'],
            'page' => $res['page'],
            'limit' => $res['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch("index");
    }

    // public function modify()
    // {
    //     $this->checkPostRequest();
    //     $post = request()->post();
    //     $rule = [
    //         'id|ID' => 'require',
    //         'field|字段' => 'require',
    //         'value|值' => 'require',
    //     ];
    //     $this->validate($post, $rule);
    //     $row = $this->model->find($post['id']);
    //     if (!$row) {
    //         $this->error('数据不存在');
    //     }
    //     $data = [];

    //     $data[$post['field']] = $post['value'];
    //     try {
    //         $row->save($data);
    //     } catch (\Exception $e) {
    //         $this->error($e->getMessage());
    //     }
    //     $this->success('保存成功');
    // }


    // public function info()
    // {
    //     if (Request()->isPost()) {
    //         $param = request()->post();
    //         $param['tags'] = !empty($param['tags']) ? implode(',', $param['tags']) : '';
    //         $res = $this->model->saveData($param);
    //         if ($res['code'] > 1) {
    //             return $this->error($res['msg']);
    //         }
    //         return $this->success($res['msg']);
    //     }

    //     $id = input('id');
    //     $where = [];
    //     $where['id'] = $id;
    //     /*        $res = $this->model->infoData($where);
    //             $info = $res['info'];*/
    //     $info = Db::name('manhua')->where($where)->find();
    //     $this->assign('info', $info);
    //     return $this->fetch();
    // }

    // /*
    //  * 删除
    //  */
    // public function del()
    // {
    //     $id = input("param.id");
    //     try {
    //         $this->model->where(["id" => $id])->delete();
    //         $this->chapterModel->where(["manhua_id" => $id])->delete();
    //         return json(["code" => 1, "msg" => "删除成功"]);
    //     } catch (\Exception $e) {
    //         return json(["code" => 0, "msg" => "删除失败"]);
    //     }
    // }

    public function chapter()
    {
        $param = input();
        $comic_id = input("param.comic_id");
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;
        $where = [];
        $where[] = ['manhua_id', '=', $comic_id];
        if (!empty($param['id'])) {
            $param['id'] = trim($param['id']);
            $where[] = ['id', '=', $param['id']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where[] = ['title', 'like', '%' . $param['wd'] . '%'];
        }
        if (in_array($param['switch'], ['0', '1'], true)) {
            $where[] = ['switch', 'eq', $param['status']];
        }
        if (in_array($param['isvip'], ['0', '1'], true)) {
            $where[] = ['isvip', 'eq', $param['isvip']];
        }
        if (in_array($param['migrate'], ['0', '1'], true)) {
            $where['migrate'] = $param['migrate'];
        }
        $order = 'sort desc';
        $res = $this->chapterModel->listData($where, $order, $param['page'], $param['limit']);
        $this->assign([
            'list' => $res['list'],
            'total' => $res['total'],
            'page' => $res['page'],
            'limit' => $res['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch();
    }

    // public function chapter_modify()
    // {
    //     $this->checkPostRequest();
    //     $post = request()->post();
    //     $rule = [
    //         'id|ID' => 'require',
    //         'field|字段' => 'require',
    //         'value|值' => 'require',
    //     ];
    //     $this->validate($post, $rule);
    //     $row = $this->chapterModel->find($post['id']);
    //     if (!$row) {
    //         $this->error('数据不存在');
    //     }
    //     $data = [];

    //     $data[$post['field']] = $post['value'];
    //     try {
    //         $row->save($data);
    //     } catch (\Exception $e) {
    //         $this->error($e->getMessage());
    //     }
    //     $this->success('保存成功');
    // }


    // public function chapter_info()
    // {
    //     if (Request()->isPost()) {
    //         $param = request()->post();
    //         $res = $this->chapterModel->saveData($param);
    //         if ($res['code'] > 1) {
    //             return $this->error($res['msg']);
    //         }
    //         return $this->success($res['msg']);
    //     }

    //     $id = input('id');
    //     $where = [];
    //     $where['id'] = $id;
    //     /*        $res = $this->model->infoData($where);
    //             $info = $res['info'];*/
    //     $info = Db::name('capter')->where($where)->find();
    //     $this->assign('info', $info);
    //     return $this->fetch();
    // }

    // /*
    //  * 删除
    //  */
    // public function chapter_del()
    // {
    //     $id = input("param.id");
    //     $result = $this->chapterModel->where(["id" => $id])->delete();
    //     if ($result) {
    //         return json(["code" => 1, "msg" => "删除成功"]);
    //     } else {
    //         return json(["code" => 0, "msg" => "删除失败"]);
    //     }
    // }

    public function photos()
    {

        $id = input("param.id");
        $images = $this->chapterModel->where('id', '=', $id)->value('imagelist');
        $data = [];
        if (!empty($images)) {
            $pic_url = Configs::get("IMAGE_HOST");
            $arr = explode(",", $images);
            foreach ($arr as $v) {
                $data[]['src'] = $pic_url . $v;
            }
        }
        $result["data"] = $data;
        $result["code"] = 1;
        return json($result);
    }

    public function clear()
    {
        $redis = new Redis();
        $keys = $redis->keys('comic*');
        if ($keys) {
            $redis->del($keys);
        }
        $this->success('清除Comic缓存成功!');
    }

    public function repeat()
    {
        $param = input();

        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;

        $where = [];

        if (!empty($param['title'])) {
            $param['title'] = trim($param['title']);
            $where[] = ['title', 'like', '%' . $param['title'] . '%'];
        }

        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }

        $duplicates = $this->model
            ->where($where)
            ->group('title')
            ->having('COUNT(*) > 1')
            ->field('title, COUNT(*) as count')
            ->page($param['page'], $param['limit'])
            ->select();

        if ($duplicates) {
            $duplicateTitles = array_column($duplicates->toArray(), 'title');

            $duplicateComics = $this->model
                ->whereIn('title', $duplicateTitles)
                ->select();

            $total = $this->model->whereIn('title', $duplicateTitles)->count();
            $page = ceil($total / $param['limit']);

            $this->assign([
                'duplicates' => $duplicateComics,
                'total' => $total,
                'page' => $param['page'],
                'limit' => $param['limit'],
            ]);

            $param['page'] = '{page}';
            $param['limit'] = '{limit}';
            $this->assign('param', $param);

            return $this->fetch('repeat');
        } else {
            return $this->error('未找到重复的漫画');
        }
    }

    public function translate()
    {
        $param = request()->post();
        $id = $param['id'] ?? 0;
        $languages = $param['languages'] ?? [];

        if (empty($id) || empty($languages)) {
            return json(['code' => 0, 'msg' => '参数缺失']);
        }

        $oldComic = OldComicModel::where('id', $id)->find();
        if (!$oldComic) {
            return json(['code' => 0, 'msg' => '未找到漫画']);
        }

        $existingComic = ComicModel::where('original_id', $id)->find();
        if ($existingComic) {
            return $this->translateExistingComic($existingComic, $languages);
        }

        return $this->migrateAndTranslateComic($oldComic, $languages);
    }

    private function translateExistingComic($comic, $languages)
    {
        $newComicId = $comic['id'];
        $existingLanguages = json_decode($comic['translated_languages'] ?? '[]', true);
        $languagesToTranslate = array_values(array_diff($languages, $existingLanguages));

        $hasChapterToTranslate = false;

        if (!empty($languagesToTranslate)) {
            // 翻译漫画本体
            Queue::push('app\index\job\TranslateManhuaInit', [
                'manhua_id' => $newComicId,
                'languages' => $languagesToTranslate,
            ], 'tran_manhua_init');
        }

        // 翻译章节
        $oldChapters = OldChapter::where('manhua_id', $comic['original_id'])->select();

        foreach ($oldChapters as $oldChapter) {
            $existingChapter = Chapter::where('original_id', $oldChapter['id'])->find();

            if ($existingChapter) {
                // 已迁移章节，检查是否还缺语言翻译
                $existingLangs = json_decode($existingChapter['translated_languages'] ?? '[]', true);
                if (!is_array($existingLangs)) $existingLangs = [];

                $needLangs = array_values(array_diff($languages, $existingLangs));
                if (empty($needLangs)) continue;

                $hasChapterToTranslate = true;

                Queue::push('app\index\job\TranslateCapterInit', [
                    'capter_id' => $existingChapter['id'],
                    'languages' => $needLangs,
                ], 'tran_capter_init');

                Queue::push('app\index\job\TranslateCapterImgJob', [
                    'capter_id' => $existingChapter['id'],
                    'languages' => $needLangs,
                ], 'tran_capter_img');

                $merged = array_values(array_unique(array_merge($existingLangs, $needLangs)));
                Chapter::where('id', $existingChapter['id'])->update([
                    'translated_languages' => json_encode($merged),
                    'update_time' => time(),
                ]);
            } else {
                $hasChapterToTranslate = true;

                // 章节尚未迁移，新建
                $chapterData = $oldChapter->getData();
                unset($chapterData['id']);
                $chapterData['manhua_id'] = $comic['id'];
                $chapterData['original_id'] = $oldChapter['id'];
                $chapterData['switch'] = '0';
                $chapterData['create_time'] = time();
                $chapterData['update_time'] = time();
                $chapterData['translated_languages'] = json_encode($languages);

                $newChapterId = Chapter::insertGetId($chapterData);

                Queue::push('app\index\job\TranslateCapterInit', [
                    'capter_id' => $newChapterId,
                    'languages' => $languages,
                ], 'tran_capter_init');

                Queue::push('app\index\job\TranslateCapterImgJob', [
                    'capter_id' => $newChapterId,
                    'languages' => $languages,
                ], 'tran_capter_img');
            }
        }

        if (!$hasChapterToTranslate && empty($languagesToTranslate)) {
            return json(['code' => 1, 'msg' => '所选语言已全部翻译，无需处理']);
        }

        // 更新漫画已翻译语言
        $mergedComicLangs = array_values(array_unique(array_merge($existingLanguages, $languagesToTranslate)));
        $comic->save([
            'translated_languages' => json_encode($mergedComicLangs),
            'update_time' => time(),
        ]);

        return json(['code' => 1, 'msg' => '增量翻译成功']);
    }

    private function migrateAndTranslateComic($oldComic, $languages)
    {
        Db::startTrans();
        try {
            // 漫画搬迁
            $data = $oldComic->getData();
            unset($data['id']);
            $data['original_id'] = $oldComic['id'];
            $data['status'] = '1';
            $data['create_time'] = time();
            $data['update_time'] = time();
            $data['translated_languages'] = json_encode($languages);

            $comic = new ComicModel();
            $comic->save($data);
            $newComicId = $comic->id;

            // 翻译漫画
            Queue::push('app\index\job\TranslateManhuaInit', [
                'manhua_id' => $newComicId,
                'languages' => $languages,
            ], 'tran_manhua_init');

            // 搬迁章节
            $oldChapters = OldChapter::where('manhua_id', $oldComic['id'])->select();
            $insertData = [];
            foreach ($oldChapters as $chapter) {
                $chapterData = $chapter->getData();
                unset($chapterData['id']);
                $chapterData['manhua_id'] = $newComicId;
                $chapterData['original_id'] = $chapter['id'];
                $chapterData['switch'] = '1';
                $chapterData['create_time'] = time();
                $chapterData['update_time'] = time();
                $chapterData['translated_languages'] = json_encode($languages);
                $insertData[] = $chapterData;
            }

            Chapter::insertAll($insertData);

            // 翻译章节
            $newChapters = Chapter::where('manhua_id', $newComicId)->select();
            foreach ($newChapters as $chapter) {
                Queue::push('app\index\job\TranslateCapterInit', [
                    'capter_id' => $chapter['id'],
                    'languages' => $languages,
                ], 'tran_capter_init');

                Queue::push('app\index\job\TranslateCapterImgJob', [
                    'capter_id' => $chapter['id'],
                    'languages' => $languages,
                ], 'tran_capter_img');
            }

            Db::commit();
            return json(['code' => 1, 'msg' => '搬迁并翻译成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '翻译失败：' . $e->getMessage()]);
        }
    }

    public function capter_translate()
    {
        $param = request()->post();
        $id = $param['id'] ?? 0;
        $languages = $param['languages'] ?? [];

        $result = $this->translateSingleChapter($id, $languages);
        return json($result);
    }

    public function batch_translate()
    {
        $param = request()->post();
        $ids = $param['ids'] ?? [];
        $languages = $param['languages'] ?? [];

        if (empty($ids) || empty($languages)) {
            return json([
                'code' => 0,
                'msg' => '参数缺失：请提供章节ID数组和语言数组'
            ]);
        }

        $successCount = 0;
        $errorList = [];

        foreach ($ids as $id) {
            try {
                $result = $this->translateSingleChapter($id, $languages);

                if ($result['code'] === 1) {
                    $successCount++;
                } else {
                    $errorList[] = "ID {$id}：{$result['msg']}";
                }
            } catch (\Throwable $e) {
                $errorList[] = "ID {$id} 异常：" . $e->getMessage();
            }
        }

        return json([
            'code' => 1,
            'msg'  => "批量处理完成，成功：{$successCount}，失败：" . count($errorList),
            'errors' => $errorList
        ]);
    }

    private function translateSingleChapter($id, $languages)
    {
        $oldChapter = OldChapter::where('id', $id)->find();
        if (!$oldChapter) return ['code' => 0, 'msg' => '未找到章节'];

        if (empty($oldChapter->manhua_id)) {
            return ['code' => 0, 'msg' => '漫画ID为空'];
        }

        $oldComic = OldComicModel::where('id', $oldChapter->manhua_id)->find();
        if (!$oldComic) return ['code' => 0, 'msg' => '未找到源漫画'];

        $comic = ComicModel::where('original_id', $oldChapter->manhua_id)->find();
        $newComicId = $comic ? $comic->id : null;

        if (!$comic) {
            // 新建漫画
            $comicData = $oldComic->getData();
            unset($comicData['id']);
            $comicData['original_id'] = $oldChapter->manhua_id;
            $comicData['create_time'] = time();
            $comicData['update_time'] = time();
            $comicData['translated_languages'] = json_encode($languages);

            $comic = new ComicModel();
            $comic->save($comicData);
            $newComicId = $comic->id;

            Queue::push('app\index\job\TranslateManhuaInit', [
                'manhua_id' => $newComicId,
                'languages' => $languages,
            ], 'tran_manhua_init');
        } else {
            $translated = json_decode($comic['translated_languages'] ?? '[]', true);
            $needLanguages = array_values(array_diff($languages, $translated));
            if (!empty($needLanguages)) {
                Queue::push('app\index\job\TranslateManhuaInit', [
                    'manhua_id' => $comic->id,
                    'languages' => $needLanguages,
                ], 'tran_manhua_init');

                $mergedLanguages = array_values(array_unique(array_merge($translated, $needLanguages)));
                ComicModel::where('id', $comic->id)->update([
                    'translated_languages' => json_encode($mergedLanguages),
                    'update_time' => time(),
                ]);
            }

            $newComicId = $comic->id;
        }

        $chapter = Chapter::where('original_id', $id)->find();
        if ($chapter) {
            $translated = json_decode($chapter['translated_languages'] ?? '[]', true);
            $needLanguages = array_values(array_diff($languages, $translated));
            if (empty($needLanguages)) {
                return ['code' => 0, 'msg' => '所有语言已翻译'];
            }

            Queue::push('app\index\job\TranslateCapterInit', [
                'capter_id' => $chapter->id,
                'languages' => $needLanguages,
            ], 'tran_capter_init');

            Queue::push('app\index\job\TranslateCapterImgJob', [
                'capter_id' => $chapter->id,
                'languages' => $needLanguages,
            ], 'tran_capter_img');

            $mergedLanguages = array_values(array_unique(array_merge($translated, $needLanguages)));
            Chapter::where('id', $chapter->id)->update([
                'translated_languages' => json_encode($mergedLanguages),
                'update_time' => time(),
            ]);

            return ['code' => 1, 'msg' => '章节已存在，增量翻译'];
        }

        // 新建章节
        $chapterData = $oldChapter->getData();
        unset($chapterData['id']);
        $chapterData['manhua_id'] = $newComicId;
        $chapterData['original_id'] = $id;
        $chapterData['create_time'] = time();
        $chapterData['update_time'] = time();
        $chapterData['translated_languages'] = json_encode($languages);

        $newChapterId = Chapter::insertGetId($chapterData);

        Queue::push('app\index\job\TranslateCapterInit', [
            'capter_id' => $newChapterId,
            'languages' => $languages,
        ], 'tran_capter_init');

        Queue::push('app\index\job\TranslateCapterImgJob', [
            'capter_id' => $newChapterId,
            'languages' => $languages,
        ], 'tran_capter_img');

        return ['code' => 1, 'msg' => '章节首次迁移并翻译'];
    }
}
