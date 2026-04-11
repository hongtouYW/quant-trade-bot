<?php

namespace app\index\controller;

use app\index\model\Admin;
use app\index\model\Chapter;
use app\index\model\ChapterTran;
use app\index\model\Comic as ComicModel;
use app\index\model\ComicTran;
use app\index\model\Configs;
use app\index\model\Ticai as TicaiModel;
use app\index\model\Tags as TagsModel;
use app\service\FtpService;
use think\cache\driver\Redis;
use think\Db;
use think\Queue;

class Task extends Base
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
        $adminId = session('admin_id');
        $where[] = ['audit_user_id', 'eq', $adminId];
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
        if (in_array($param['capter_audit'], ['0', '1', '2'], true)) {
            $where[] = ['capter_audit', 'eq', $param['capter_audit']];
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

        $this->assign([
            'list' => $res['list'],
            'total' => $res['total'],
            'page' => $res['page'],
            'limit' => $res['limit'],
            'admins' => $admins,
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch("index");
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
                            'id'                 => $manhuaId,
                            'title'              => $trans['title'] ?? '',
                            'desc'               => $trans['desc'] ?? '',
                            'image'              => $trans['image'] ?? '',
                            'cover_horizontal'   => $trans['cover_horizontal'] ?? '',
                            'keyword'            => $trans['keyword'] ?? '',
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
                if (!$actorId || empty($actor['trans'])) continue;

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
            'lang_code'          => 'zh',
            'title'              => $info['title'] ?? '',
            'desc'               => $info['desc'] ?? '',
            'image'              => $info['image'] ?? '',
            'cover_horizontal'   => $info['cover_horizontal'] ?? '',
            'keyword'            => $info['keyword'] ?? '',
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
            'info'         => $info,
            'actors'       => $actors,
            'highlights'   => $highlights,
            'selectedTags' => $selectedTags,
            'translations' => $translationsByLang,
            'img_domain'   => $img_domain,
            'langList'     => $langList,
        ]);
        return $this->fetch();
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
            $where[] = ['switch', 'eq', $param['status']];
        }
        if (in_array($param['isvip'], ['0', '1'], true)) {
            $where[] = ['isvip', 'eq', $param['isvip']];
        }
        if (in_array($param['audit_status'], ['0', '1', '2'], true)) {
            $where[] = ['audit_status', 'eq', $param['audit_status']];
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

    public function modify()
    {
        $this->checkPostRequest();
        $post = request()->post();

        $rule = [
            'id|ID'    => 'require',
            'field|字段' => 'require',
            'value|值'  => 'require',
        ];
        $this->validate($post, $rule);

        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }

        $data = [];
        $data[$post['field']] = $post['value'];

        // 如果是更新 status，同时更新 audit_time（只有状态变化时才更新）
        if ($post['field'] === 'status' && $row->status != $post['value']) {
            $data['audit_time'] = time();
        }

        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('保存成功');
    }


    public function chapter_modify()
    {
        $this->checkPostRequest();
        $post = request()->post();

        $rule = [
            'id|ID'    => 'require',
            'field|字段' => 'require',
            'value|值'  => 'require',
        ];
        $this->validate($post, $rule);

        $row = $this->chapterModel->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }

        $data = [];
        $data[$post['field']] = $post['value'];

        // 如果是更新 audit_status，同时更新 audit_time
        if ($post['field'] === 'audit_status' && $row->audit_status != $post['value']) {
            $data['audit_time'] = time();
        }

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

    public function edit_chapter_translation()
    {
        $id = input('id');
        $chapter = Db::name('capter')->find($id);
        $translations = Db::name('capter_trans')->where('capter_id', $id)->select();

        if (empty($translations)) {
            $translations = [[
                'id' => 0,
                'capter_id' => $id,
                'lang_code' => 'en',
                'title' => '',
                'imagelist' => '',
            ]];
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

    public function getTask()
    {
        $adminId = session('admin_id');
        if (!$adminId) {
            return json(['code' => 0, 'msg' => '请先登录管理员账号']);
        }

        // 当前管理员已领取任务数
        $count = Db::name('manhua')
            ->where('audit_user_id', $adminId)
            ->where('status', 0)
            ->count();

        if ($count >= 10) {
            return json(['code' => 0, 'msg' => '领取失败，每个管理员最多只能领取 10 个任务']);
        }

        // 还能再领取多少
        $remain = 10 - $count;

        // 查询符合条件的未分配漫画
        $subQuery = Db::name('capter')
            ->alias('c')
            ->whereRaw('c.manhua_id = m.id')
            ->where('c.translate_status', 1)
            ->where('c.translate_img', 2)
            ->buildSql();

        $tasks = Db::name('manhua')
            ->alias('m')
            ->where('m.translate_status', 1)
            ->where('m.audit_user_id', 0)
            ->where('m.status', 0)
            ->whereExists($subQuery)
            ->order('m.update_time desc')
            ->limit($remain)
            ->select();

        if (empty($tasks)) {
            return json(['code' => 0, 'msg' => '暂无可领取的任务']);
        }

        // 批量更新任务归属
        $ids = array_column($tasks, 'id');
        Db::name('manhua')
            ->whereIn('id', $ids)
            ->update([
                'audit_user_id' => $adminId,
                'audit_time'   => time()
            ]);

        return json(['code' => 1, 'msg' => '成功领取 ' . count($ids) . ' 个任务']);
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
}
