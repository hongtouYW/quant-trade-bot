<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;

use app\index\model\Admin;
use app\index\model\Chapter;
use app\index\model\ChapterTran;
use app\index\model\Comic as ComicModel;
use app\index\model\ComicTran;
use app\index\model\Configs;
use app\index\model\Ticai as TicaiModel;  //标签
use app\index\model\Tags as TagsModel;  //标签
use app\service\FtpService;
use think\cache\driver\Redis;
use think\Db;
use think\Queue;

class Comic extends Base
{
    protected $model = '';
    protected $chapterModel = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new ComicModel;
        $this->chapterModel = new Chapter();
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
            $where[] = ['id', '=', $param['id']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where[] = ['title', 'like', '%' . $param['wd'] . '%'];
        }
        if (in_array($param['status'], ['0', '1', '2'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }
        if (in_array($param['issole'], ['0', '1'], true)) {
            $where[] = ['issole', 'eq', $param['issole']];
        }
        if (in_array($param['isjingpin'], ['0', '1'], true)) {
            $where[] = ['isjingpin', 'eq', $param['isjingpin']];
        }
        if (in_array($param['xianmian'], ['0', '1'], true)) {
            $where[] = ['xianmian', 'eq', $param['xianmian']];
        }
        if (in_array($param['mhstatus'], ['0', '1'], true)) {
            $where[] = ['mhstatus', 'eq', $param['mhstatus']];
        }
        if (!empty($param['ticai_id'])) {
            $param['ticai_id'] = trim($param['ticai_id']);
            $where[] = ['ticai_id', '=', $param['ticai_id']];
        }
        if (in_array($param['translate_status'], ['0', '1'], true)) {
            $where[] = ['translate_status', 'eq', $param['translate_status']];
        }
        if (in_array($param['actor_translate'], ['0', '1'], true)) {
            $where[] = ['actor_translate', 'eq', $param['actor_translate']];
        }
        if (in_array($param['capter_img_translate'], ['0', '1', '2'], true)) {
            $where[] = ['capter_img_translate', 'eq', $param['capter_img_translate']];
        }
        if (in_array($param['audit_user_id'], ['0', '1'], true)) {
            if ($param['audit_user_id'] == '0') {
                // 未分配
                $where[] = ['audit_user_id', 'eq', 0];
            } elseif ($param['audit_user_id'] == '1') {
                // 已分配
                $where[] = ['audit_user_id', 'neq', 0];
            }
        }
        if (!empty($param['audit_filter'])) {
            switch ($param['audit_filter']) {
                case 'not_audit':
                    $subQuery = Db::name('capter')
                        ->field('manhua_id')
                        ->group('manhua_id')
                        ->having('SUM(is_audit) = 0')
                        ->buildSql();
                    $where[] = ['id', 'in', Db::raw($subQuery)];
                    break;

                case 'partial_audit':
                    $subQuery = Db::name('capter')
                        ->field('manhua_id')
                        ->group('manhua_id')
                        ->having('SUM(is_audit) > 0 AND SUM(is_audit) < COUNT(*)') // 有已审核也有未审核
                        ->buildSql();
                    $where[] = ['id', 'in', Db::raw($subQuery)];
                    break;

                case 'all_audit':
                    $subQuery = Db::name('capter')
                        ->field('manhua_id')
                        ->group('manhua_id')
                        ->having('SUM(is_audit) = COUNT(*)')
                        ->buildSql();
                    $where[] = ['id', 'in', Db::raw($subQuery)];
                    break;
            }
        }
        if (!empty($param['cjname'])) {
            $param['cjname'] = trim($param['cjname']);
            $where[] = ['cjname', '=', $param['cjname']];
        }
        $order = 'm.update_time desc';
        $res = $this->model->listData($where, $order, $param['page'], $param['limit']);

        foreach ($res['list'] as &$comic) {
            $chapters = Db::name('capter')
                ->where('manhua_id', $comic['id'])
                ->field('is_audit')
                ->select();

            if (empty($chapters)) {
                $comic['audit_progress'] = '无章节';
            } else {
                $total = count($chapters);
                $audited = count(array_filter($chapters, fn($c) => $c['is_audit'] == 1));

                if ($audited == 0) {
                    $comic['audit_progress'] = '未审核';
                } elseif ($audited == $total) {
                    $comic['audit_progress'] = '已全部审核';
                } else {
                    $comic['audit_progress'] = "部分审核 ({$audited}/{$total})";
                }
            }
        }

        // 取所有管理员
        $admins = Admin::field('id,username')
            ->select()
            ->toArray();

        $cjnameList = Db::name('manhua')
            ->whereNotNull('cjname')
            ->where('cjname', '<>', '')
            ->group('cjname')
            ->column('cjname');

        $this->assign([
            'list' => $res['list'],
            'total' => $res['total'],
            'page' => $res['page'],
            'limit' => $res['limit'],
            'admins' => $admins,
            'cjnameList' => $cjnameList,
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch("index");
    }

    public function modify()
    {
        $this->checkPostRequest();
        $post = request()->post();
        $rule = [
            'id|ID' => 'require',
            'field|字段' => 'require',
            'value|值' => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        $data = [];

        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }


    public function info()
    {
        if (request()->isPost()) {
            $param = request()->post();
            $manhuaId = $param['id'] ?? 0;

            // 格式化 tags
            $param['tags'] = !empty($param['tags']) ? implode(',', $param['tags']) : '';

            // ✅ 保存主数据
            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }

            // ✅ 保存多语言翻译
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {

                    // 中文 zh => 直接更新主表，不进 manhua_trans
                    if ($lang === 'zh') {
                        $data = [
                            'id' => $manhuaId,
                            'title' => $trans['title'] ?? '',
                            'desc' => $trans['desc'] ?? '',
                            'image' => $trans['image'] ?? '',
                            'cover_horizontal' => $trans['cover_horizontal'] ?? '',
                            'keyword' => $trans['keyword'] ?? '',
                            'last_chapter_title' => $trans['last_chapter_title'] ?? '',
                        ];

                        // 使用 save 并指定条件，触发 afterWrite
                        (new ComicModel())->save($data, ['id' => $manhuaId]);

                        continue;
                    }

                    // 其它语言 => 插入或更新 manhua_trans
                    $transData = [
                        'manhua_id' => $manhuaId,
                        'lang_code' => $lang,
                        'title' => $trans['title'] ?? '',
                        'desc' => $trans['desc'] ?? '',
                        'image' => $trans['image'] ?? '',
                        'cover_horizontal' => $trans['cover_horizontal'] ?? '',
                        'keyword' => $trans['keyword'] ?? '',
                        'last_chapter_title' => $trans['last_chapter_title'] ?? '',
                    ];

                    insertOrUpdate('qiswl_manhua_trans', ['manhua_id' => $manhuaId, 'lang_code' => $lang], $transData);
                }
            }

            // ✅ 同步角色
            $actors = $param['actors'] ?? [];
            $actorIdMap = syncSubTable(
                'manhua_actors',
                $manhuaId,
                $actors,
                ['name', 'img']
            );

            // ✅ 同步角色翻译
            foreach ($actors as $index => $actor) {
                $actorId = $actor['id'] ?? $actorIdMap[$index] ?? null;
                if (!$actorId || empty($actor['trans']))
                    continue;

                foreach ($actor['trans'] as $lang => $transData) {
                    $data = [
                        'manhua_actor_id' => $actorId,
                        'lang_code' => $lang,
                        'name' => $transData['name'] ?? '',
                    ];
                    insertOrUpdate('qiswl_manhua_actor_trans', [
                        'manhua_actor_id' => $actorId,
                        'lang_code' => $lang
                    ], $data);
                }
            }

            // ✅ 同步精彩片段
            syncSubTable(
                'manhua_highlights',
                $manhuaId,
                $param['highlights'] ?? [],
                ['name', 'img']
            );

            return $this->success($res['msg']);
        }

        // GET 请求 - 加载数据
        $id = input('id');

        // 获取主信息
        $info = Db::name('manhua')->where('id', $id)->find();

        // 获取 tags
        $selectedTags = [];
        if (!empty($info['tags'])) {
            $tagIds = explode(',', $info['tags']);
            $selectedTags = Db::table('tags')
                ->whereIn('id', $tagIds)
                ->field('id as value, name')
                ->select();
        }

        // 获取角色和角色翻译
        $actors = Db::name('manhua_actors')
            ->where('manhua_id', $id)
            ->select();

        $actorIds = array_column($actors, 'id');
        $actorTrans = [];
        if (!empty($actorIds)) {
            $actorTransRows = Db::name('manhua_actor_trans')
                ->whereIn('manhua_actor_id', $actorIds)
                ->select();

            foreach ($actorTransRows as $row) {
                $actorTrans[$row['manhua_actor_id']][$row['lang_code']] = $row;
            }
            // 拼接进 actors
            foreach ($actors as &$actor) {
                $actor['trans'] = $actorTrans[$actor['id']] ?? [];
            }
            unset($actor);
        }

        // 获取精彩片段
        $highlights = Db::name('manhua_highlights')
            ->where('manhua_id', $id)
            ->select();

        // 获取所有语言翻译
        $translations = Db::name('manhua_trans')
            ->where('manhua_id', $id)
            ->select();

        // -------- 重点：把中文拼到 translations --------
        $zhTranslation = [
            'lang_code' => 'zh',
            'title' => $info['title'] ?? '',
            'desc' => $info['desc'] ?? '',
            'image' => $info['image'] ?? '',
            'cover_horizontal' => $info['cover_horizontal'] ?? '',
            'keyword' => $info['keyword'] ?? '',
            'last_chapter_title' => $info['last_chapter_title'] ?? '',
        ];

        // 转换其它语言翻译为以 lang_code 为 key 的数组
        $translationsByLang = [];
        foreach ($translations as $row) {
            $translationsByLang[$row['lang_code']] = $row;
        }

        // 插入中文翻译到开头
        $translationsByLang = array_merge(['zh' => $zhTranslation], $translationsByLang);

        // 图片域名
        $img_domain = Configs::get('IMAGE_HOST');

        // 语言列表（如有多语言支持，建议从配置或数据库读取）
        $langList = [['lang_code' => 'en']];

        $this->assign([
            'info' => $info,
            'actors' => $actors,
            'highlights' => $highlights,
            'selectedTags' => $selectedTags,
            'translations' => $translationsByLang,
            'img_domain' => $img_domain,
            'langList' => $langList,
        ]);
        return $this->fetch();
    }

    /*
     * 删除
     */
    public function del()
    {
        $id = input("param.id");
        try {
            ComicModel::destroy(['id' => $id]);
            Chapter::destroy(function ($query) use ($id) {
                $query->where('manhua_id', $id);
            });
            return json(["code" => 1, "msg" => "删除成功"]);
        } catch (\Exception $e) {
            return json(["code" => 0, "msg" => "删除失败"]);
        }
    }

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
            $where[] = ['switch', 'eq', $param['switch']];
        }
        if (in_array($param['isvip'], ['0', '1'], true)) {
            $where[] = ['isvip', 'eq', $param['isvip']];
        }
        if (in_array($param['translate_status'], ['0', '1'], true)) {
            $where[] = ['translate_status', 'eq', $param['translate_status']];
        }
        if (in_array($param['translate_img'], ['0', '1', '2', '3', '4'], true)) {
            $where[] = ['translate_img', 'eq', $param['translate_img']];
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

    public function chapter_modify()
    {
        $this->checkPostRequest();
        $post = request()->post();
        $rule = [
            'id|ID' => 'require',
            'field|字段' => 'require',
            'value|值' => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->chapterModel->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        $data = [];

        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }


    public function chapter_info()
    {
        if (Request()->isPost()) {
            $param = request()->post();
            $res = $this->chapterModel->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where = [];
        $where['id'] = $id;
        /*        $res = $this->model->infoData($where);
                $info = $res['info'];*/
        $info = Db::name('capter')->where($where)->find();
        $this->assign('info', $info);
        return $this->fetch();
    }

    /*
     * 删除
     */
    public function chapter_del()
    {
        $id = input("param.id");
        $result = $this->chapterModel->where(["id" => $id])->delete();
        if ($result) {
            return json(["code" => 1, "msg" => "删除成功"]);
        } else {
            return json(["code" => 0, "msg" => "删除失败"]);
        }
    }

    public function photos()
    {
        $id = input("param.id");
        $lang = input("param.lang", "zh");

        if ($lang === 'zh') {
            // 中文从主表获取
            $images = $this->chapterModel->where('id', '=', $id)->value('imagelist');
        } else {
            // 其他语言从翻译表获取
            $images = Db::name('capter_trans')
                ->where('capter_id', '=', $id)
                ->where('lang_code', '=', $lang)
                ->value('imagelist');
        }

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

        $param['page'] = !empty($param['page']) ? (int) $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? (int) $param['limit'] : 10;

        $where = [];

        if (!empty($param['title'])) {
            $param['title'] = trim($param['title']);
            $where[] = ['title', 'like', '%' . $param['title'] . '%'];
        }

        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }

        // 找到重复 title
        $duplicateTitles = $this->model
            ->where($where)
            ->group('title')
            ->having('COUNT(*) > 1')
            ->column('title');

        if (empty($duplicateTitles)) {
            $this->assign('noDuplicates', '未找到重复的漫画');
        }

        // 查询所有重复的漫画，并按 title 排序
        $duplicateComics = $this->model
            ->where($where)
            ->whereIn('title', $duplicateTitles)
            ->order('title asc, id asc')
            ->page($param['page'], $param['limit'])
            ->select();

        // 总数 & 页数
        $total = $this->model
            ->where($where)
            ->whereIn('title', $duplicateTitles)
            ->count();

        $page = ceil($total / $param['limit']);

        // 分配数据到模板
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
    }

    public function repeat_chapter()
    {
        $param = input();

        $param['page'] = !empty($param['page']) ? (int) $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? (int) $param['limit'] : 10;

        $where = [];

        // 普通条件不用包含 title（title 单独处理）
        if (!empty($param['manhua_id'])) {
            $where[] = ['d.manhua_id', '=', (int) $param['manhua_id']];
        }
        if (!empty($param['capter_id'])) {
            $where[] = ['d.capter_id', '=', (int) $param['capter_id']];
        }
        if (!empty($param['date_start']) && !empty($param['date_end'])) {
            $where[] = ['d.created_at', 'between', [$param['date_start'], $param['date_end']]];
        }

        // 构建基础 query（不含 title）
        $listQuery = Db::name('duplicate_chapters')
            ->alias('d')
            ->join('manhua m', 'm.id=d.manhua_id', 'LEFT')
            ->join('capter c', 'c.id=d.capter_id', 'LEFT')
            ->where($where);

        // title 用 whereRaw 单独绑定，避免框架自动加反引号导致语法错误
        if (!empty($param['title'])) {
            // 规范化用户输入：去掉所有空白
            $param['title'] = preg_replace('/\s+/', '', trim($param['title']));
            // 使用参数绑定，防注入
            $listQuery->whereRaw("REPLACE(d.title, ' ', '') LIKE ?", ['%' . $param['title'] . '%']);
        }

        // 查询列表
        $chapters = $listQuery
            ->field('d.id as dup_id, d.title, d.manhua_id, d.capter_id, m.title as manhua_title, c.*')
            ->order('d.manhua_id asc, d.title asc, d.capter_id asc')
            ->page($param['page'], $param['limit'])
            ->select();

        // 统计总数（单独构建 countQuery，和上面应用同样的 where/whereRaw）
        $countQuery = Db::name('duplicate_chapters')->alias('d')->where($where);
        if (!empty($param['title'])) {
            $countQuery->whereRaw("REPLACE(d.title, ' ', '') LIKE ?", ['%' . $param['title'] . '%']);
        }
        $total = $countQuery->count();

        $pageCount = ceil($total / $param['limit']);
        $img_domain = Configs::get('IMAGE_HOST');

        // 分配模板数据
        $this->assign([
            'list' => $chapters,
            'total' => $total,
            'page' => $param['page'],
            'pageCount' => $pageCount,
            'limit' => $param['limit'],
            'img_domain' => $img_domain,
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        return $this->fetch('repeat_chapter');
    }

    public function delete_duplicate_chapter()
    {
        $id = input('id/d', 0); // duplicate_chapters 表里的 id
        if (empty($id)) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        // 获取 duplicate_chapters 记录
        $dup = Db::name('duplicate_chapters')->where('id', $id)->find();
        if (!$dup) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        $chapterId = $dup['capter_id'];

        // 找到章节信息
        $chapter = Db::name('capter')->where('id', $chapterId)->find();
        if (!$chapter) {
            Db::name('duplicate_chapters')->where('id', $id)->delete();
            return json(['code' => 1, 'msg' => '章节不存在，重复记录已清理']);
        }

        // 检查是否还有其他重复章节（同一漫画 + 同一章节名）
        $count = Db::name('capter')
            ->where('manhua_id', $chapter['manhua_id'])
            ->where('title', $chapter['title'])
            ->count();

        if ($count <= 1) {
            return json(['code' => 0, 'msg' => '至少保留一个章节，不能全部删除']);
        }

        Db::startTrans();
        try {
            // 1. 删除翻译
            Db::name('capter_trans')->where('capter_id', $chapterId)->delete();

            // 2. 删除章节
            Db::name('capter')->where('id', $chapterId)->delete();

            // 3. 删除 duplicate 记录
            Db::name('duplicate_chapters')->where('id', $id)->delete();

            Db::commit();
            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '删除失败: ' . $e->getMessage()]);
        }
    }

    public function batch_delete_duplicate_chapter()
    {
        $ids = input('ids/a', []); // 获取数组参数 ids[]=1&ids[]=2
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '参数错误，至少传一个ID']);
        }

        // 获取要删除的 duplicate_chapters 记录
        $duplicates = Db::name('duplicate_chapters')->whereIn('id', $ids)->select();
        if (empty($duplicates)) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        Db::startTrans();
        try {
            foreach ($duplicates as $dup) {
                $chapterId = $dup['capter_id'];

                // 找到章节信息
                $chapter = Db::name('capter')->where('id', $chapterId)->find();
                if (!$chapter) {
                    Db::name('duplicate_chapters')->where('id', $dup['id'])->delete();
                    throw new \Exception("章节不存在 (ID: {$chapterId})");
                }

                // 检查是否还有其他重复章节（同一漫画 + 同一章节名）
                $count = Db::name('capter')
                    ->where('manhua_id', $chapter['manhua_id'])
                    ->where('title', $chapter['title'])
                    ->count();

                if ($count <= 1) {
                    throw new \Exception("章节 {$chapter['title']} 至少保留一个，不能全部删除");
                }

                // 1. 删除翻译
                Db::name('capter_trans')->where('capter_id', $chapterId)->delete();

                // 2. 删除章节
                Db::name('capter')->where('id', $chapterId)->delete();

                // 3. 删除 duplicate 记录
                Db::name('duplicate_chapters')->where('id', $dup['id'])->delete();
            }

            Db::commit();
            return json(['code' => 1, 'msg' => '批量删除成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '删除失败: ' . $e->getMessage()]);
        }
    }

    public function batch_delete_chapter()
    {
        $ids = input('ids/a', []); // 获取数组参数 ids[]=1&ids[]=2
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => '参数错误，至少传一个ID']);
        }

        // 获取要删除的章节记录
        $chapters = Db::name('capter')->whereIn('id', $ids)->select();
        if (empty($chapters)) {
            return json(['code' => 0, 'msg' => '章节不存在']);
        }

        Db::startTrans();
        try {
            foreach ($chapters as $chapter) {
                $chapterId = $chapter['id'];

                // 删除翻译
                Db::name('capter_trans')->where('capter_id', $chapterId)->delete();

                // 删除章节
                Db::name('capter')->where('id', $chapterId)->delete();
            }

            Db::commit();
            return json(['code' => 1, 'msg' => '批量删除章节成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => '删除失败: ' . $e->getMessage()]);
        }
    }

    public function translate()
    {
        if (Request()->isPost()) {
            $param = request()->post();

            $manhuaId = $param['id'] ?? null;
            $languages = $param['languages'] ?? null;
            if (!$manhuaId || !$languages) {
                return $this->error('缺少漫画ID或语言项');
            }

            $manhua = Db::name('manhua')->find($manhuaId);
            if (!$manhua) {
                return $this->error('漫画不存在');
            }

            $capters = [];
            if (!empty($param['cp_text_trans']) || !empty($param['cp_img_trans'])) {
                $capters = Db::name('capter')
                    ->where('manhua_id', $manhuaId)
                    ->field('id, translate_img')
                    ->select();
            }

            if (!empty($param['mh_text_trans'])) {
                Queue::push('app\index\job\TranslateManhuaInit', [
                    'manhua_id' => $manhuaId,
                    'languages' => $languages,
                ], 'tran_manhua_init');
            }

            if (!empty($param['cp_text_trans']) && $capters) {
                foreach ($capters as $capter) {
                    Queue::push('app\index\job\TranslateCapterInit', [
                        'capter_id' => $capter['id'],
                        'languages' => $languages,
                    ], 'tran_capter_init');
                }
            }

            if (!empty($param['cp_img_trans']) && $capters) {
                foreach ($capters as $capter) {
                    if ($capter['translate_img'] != 2) {
                        Queue::push('app\index\job\TranslateCapterImgJob', [
                            'capter_id' => $capter['id']
                        ], 'tran_capter_img');
                    }
                }
            }

            if (!empty($param['mh_actor_trans'])) {
                Queue::push('app\index\job\TranslateManhuaActorInit', [
                    'manhua_id' => $manhuaId,
                    'languages' => $languages,
                ]);
            }

            return $this->success('成功');
        }

        $id = input('id');
        $info = Db::name('manhua')->where('id', $id)->find();
        $this->assign('info', $info);
        return $this->fetch();
    }

    public function capterTranslate()
    {
        if (Request()->isPost()) {
            $param = request()->post();

            $capterId = $param['id'] ?? null;
            if (!$capterId) {
                return $this->error('缺少章节ID');
            }

            $capter = Db::name('capter')->find($capterId);
            if (!$capter) {
                return $this->error('漫画不存在');
            }

            if (!empty($param['cp_text_trans'])) {
                Queue::push('app\index\job\TranslateCapterInit', [
                    'capter_id' => $capterId
                ], 'tran_capter_init');
            }

            if (!empty($param['cp_img_trans']) && $capter['translate_img'] != 2) {
                Queue::push('app\index\job\TranslateCapterImgJob', [
                    'capter_id' => $capterId
                ], 'tran_capter_img');
            }

            return $this->success('成功');
        }

        $id = input('id');
        $info = Db::name('capter')->where('id', $id)->find();
        $this->assign('info', $info);
        return $this->fetch();
    }

    public function edit_translation()
    {
        $id = input('id');
        $manhua = Db::name('manhua')->find($id);

        // 获取所有语言的翻译
        $translations = Db::name('manhua_trans')->where('manhua_id', $id)->select();

        // 获取演员和对应翻译
        $actors = Db::name('manhua_actors')->where('manhua_id', $id)->select();
        foreach ($actors as &$actor) {
            $actor['trans'] = Db::name('manhua_actor_trans')->where('manhua_actor_id', $actor['id'])->select();
        }

        $img_domain = Configs::get('IMAGE_HOST');

        $this->assign(compact('manhua', 'translations', 'actors', 'img_domain'));
        return $this->fetch(); // 视图页面 edit_translation.html
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        // 更新 manhua_trans 表
        $comicTran = ComicTran::find($id);
        if ($comicTran) {
            $comicTran->save([
                'title' => $data['title'],
                'desc' => $data['desc'],
                'keyword' => $data['keyword'],
                'last_chapter_title' => $data['last_chapter_title'],
                'image' => $data['image'],
                'cover_horizontal' => $data['cover_horizontal'],
            ]);
        }

        // 更新演员翻译
        if (!empty($data['actor'])) {
            foreach ($data['actor'] as $actor_id => $lang_arr) {
                $name = $lang_arr[$lang];
                $exists = Db::name('manhua_actor_trans')->where([
                    'manhua_actor_id' => $actor_id,
                    'lang_code' => $lang,
                ])->find();

                if ($exists) {
                    Db::name('manhua_actor_trans')->where('id', $exists['id'])->update(['name' => $name]);
                } else {
                    Db::name('manhua_actor_trans')->insert([
                        'manhua_actor_id' => $actor_id,
                        'lang_code' => $lang,
                        'name' => $name
                    ]);
                }
            }
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function upload()
    {
        // 获取上传文件对象（Layui 默认字段名为 'file'）
        $file = request()->file('file');

        if (!$file) {
            return json(['code' => 1, 'msg' => '未检测到上传文件']);
        }

        // 获取原始文件名以提取扩展名
        $info = $file->getInfo();
        $originalName = $info['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 安全性：限制允许的扩展名
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowedExts)) {
            return json(['code' => 1, 'msg' => '不支持的文件类型']);
        }

        // 生成唯一文件名
        $filename = uniqid('img_') . '.' . $ext;

        // 保存路径：public/temp/
        $savePath = ROOT_PATH . 'public' . DIRECTORY_SEPARATOR . 'uploads/temp';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        // 移动文件
        $info = $file->move($savePath, $filename);
        if ($info) {
            $url = '/uploads/temp/' . $filename;

            // 兼容 Layui 返回格式
            return json([
                'code' => 1,
                'msg' => '上传成功',
                'data' => ['src' => $url]
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => $file->getError()
            ]);
        }
    }

    public function edit_chapter_translation()
    {
        $id = input('id');
        $chapter = Db::name('capter')->find($id);
        $translations = Db::name('capter_trans')->where('capter_id', $id)->select();

        if (empty($translations)) {
            $translations = [
                [
                    'id' => 0,
                    'capter_id' => $id,
                    'lang_code' => 'en',
                    'title' => '',
                    'imagelist' => '',
                ]
            ];
        }

        $this->assign(compact('chapter', 'translations'));
        return $this->fetch(); // 视图页面 edit_translation.html
    }

    public function save_chapter_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::name('capter_trans')->insert([
                'capter_id' => $data['capter_id'],
                'lang_code' => $lang,
                'title' => $data['title'],
                'imagelist' => $data['imagelist'],
            ]);
        } else {
            // 更新翻译
            Db::name('capter_trans')->where('id', $id)->update([
                'title' => $data['title'],
                'imagelist' => $data['imagelist'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function search()
    {
        $keyword = request()->get('wd');
        if (!$keyword) {
            return json(['list' => []]);
        }

        $tags = Db::table('tags')
            ->where('name', 'like', '%' . $keyword . '%')
            ->limit(20)
            ->field('id as value, name')
            ->select();

        return json(['list' => $tags]);
    }

    public function assignTask()
    {
        $data = input('post.');

        $ids = isset($data['ids']) ? $data['ids'] : [];
        $admin_id = isset($data['admin_id']) ? intval($data['admin_id']) : 0;

        if (empty($ids) || !$admin_id) {
            return json(['code' => 0, 'msg' => '请选择漫画和管理员']);
        }

        // 查询漫画
        $manhuaList = Db::name('manhua')
            ->whereIn('id', $ids)
            ->where('translate_status', 1)   // 已翻译
            ->where('status', 0)       // 待审核
            ->select();

        if (empty($manhuaList)) {
            return json(['code' => 0, 'msg' => '没有符合条件的漫画可分配']);
        }

        $assignIds = [];
        $skipIds = [];

        foreach ($manhuaList as $manhua) {
            // 查询漫画下的章节是否全部已翻译且待审核
            $chapterCount = Db::name('capter')
                ->where('manhua_id', $manhua['id'])
                ->where('translate_status', 1)
                ->where('translate_img', 2)
                ->where('audit_status', 0)
                ->count();

            // 查询漫画下的章节总数
            $totalChapterCount = Db::name('capter')
                ->where('manhua_id', $manhua['id'])
                ->count();

            if ($totalChapterCount > 0 && $chapterCount == $totalChapterCount) {
                // 漫画和章节都符合条件，可以分配
                $assignIds[] = $manhua['id'];
            } else {
                $skipIds[] = [
                    'id' => $manhua['id'],
                    'title' => $manhua['title'],
                    'reason' => '漫画或章节未全部翻译或已审核'
                ];
            }
        }

        if (!empty($assignIds)) {
            // 批量更新漫画表 audit_user_id
            Db::name('manhua')->whereIn('id', $assignIds)->update(['audit_user_id' => $admin_id, 'audit_time' => time()]);
        }

        return json([
            'code' => 1,
            'msg' => '分配完成',
            'data' => [
                'assigned' => $assignIds, // 成功分配的漫画ID
                'skipped' => $skipIds     // 无法分配的漫画及原因
            ]
        ]);
    }

    // 切换人工审核状态
    public function chapter_audit()
    {
        if (!$this->request->isAjax()) {
            return json(['code' => 0, 'msg' => '非法请求']);
        }

        $id = input('post.id/d');
        if (!$id) {
            return json(['code' => 0, 'msg' => '参数错误']);
        }

        $chapter = Db::name('capter')->find($id);
        if (!$chapter) {
            return json(['code' => 0, 'msg' => '章节不存在']);
        }

        $newStatus = !empty($chapter['is_audit']) ? 0 : 1;

        try {
            Db::name('capter')->where('id', $id)->update(['is_audit' => $newStatus]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '保存失败: ' . $e->getMessage()]);
        }

        return json([
            'code' => 1,
            'msg' => $newStatus ? '已审核' : '已取消审核',
            'status' => $newStatus
        ]);
    }

    public function clear_comic()
    {

        $id = input('post.id');
        $page = input('post.page', 1);

        if (!$id) {
            return $this->error("缺少漫画ID");
        }

        $urls = [
            "/data/comic/info-{$id}-asc-{$page}-10-zh.js",
            "/data/comic/info-{$id}-asc-{$page}-10-en.js",
        ];

        $baseUrl = config('js_cache_clear_url');

        $client = new \GuzzleHttp\Client(['timeout' => 10]);

        try {
            foreach ($urls as $url) {
                $client->get($baseUrl . $url);
            }
        } catch (\Exception $e) {
            return $this->error("清除缓存失败：" . $e->getMessage());
        }

        return $this->success('清除缓存成功!');
    }

    public function clear_chapter()
    {

        $id = input('post.id');

        if (!$id) {
            return $this->error("缺少章节ID");
        }

        $urls = [
            "/data/comic/chapterInfo-{$id}-zh.js",
            "/data/comic/chapterInfo-{$id}-en.js",
        ];

        $baseUrl = config('js_cache_clear_url');

        $client = new \GuzzleHttp\Client(['timeout' => 10]);

        try {
            foreach ($urls as $url) {
                $client->get($baseUrl . $url);
            }
        } catch (\Exception $e) {
            return $this->error("清除缓存失败：" . $e->getMessage());
        }

        return $this->success('清除缓存成功!');
    }
}
