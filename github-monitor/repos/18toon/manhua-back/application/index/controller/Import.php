<?php
namespace app\index\controller;

use think\Db;
use think\facade\Log;
use think\Queue;
use app\index\model\Configs;
use app\index\model\Ticai as TicaiModel;
use app\index\model\Tags as TagsModel;
use app\service\ImportManhuaService;

class Import extends Base
{
    protected $model;
    protected $chapterModel;
    protected $importService;


    public function initialize()
    {
        parent::initialize();
        $this->model = Db::name('import_manhua');
        $this->chapterModel = Db::name('import_capter');
        $ticaiList = TicaiModel::field('id,name')->where('switch', '=', 1)->order('id asc')->select();
        $this->assign('ticaiList', $ticaiList);
        $tagList = TagsModel::field('id,name')->where('status', '=', 1)->order('sort desc')->select();
        $this->assign('tagList', $tagList);

        $this->importService = new ImportManhuaService();
    }

    /**
     * 漫画列表
     */
    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;

        $where = [];
        if (!empty($param['id']))
            $where[] = ['id', '=', $param['id']];
        if (!empty($param['external_id']))
            $where[] = ['external_id', '=', $param['external_id']];
        if (!empty($param['title']))
            $where[] = ['title', 'like', "%{$param['title']}%"];
        if (isset($param['status']) && $param['status'] !== '')
            $where[] = ['status', '=', $param['status']];
        if (isset($param['mhstatus']) && $param['mhstatus'] !== '')
            $where[] = ['mhstatus', '=', $param['mhstatus']];

        // 仅转换但章节未全转换
        if (isset($param['incomplete']) && $param['incomplete'] == 1) {
            $prefix = config('database.prefix');
            $table = $prefix . 'import_capter';

            $ids = Db::name('import_manhua')
                ->alias('m')
                ->where('m.is_converted', 1)
                ->whereExists(function ($query) use ($table) {
                    $query->table($table)
                        ->whereRaw("{$table}.manhua_id = m.id")
                        ->where('is_converted', 0);
                })
                ->column('id');

            $where[] = !empty($ids) ? ['id', 'in', $ids] : ['id', '=', 0];
        }

        if (isset($param['untranslated']) && $param['untranslated'] == 1) {
            $prefix = config('database.prefix');
            $tableTrans = $prefix . 'import_manhua_trans';

            $ids = Db::name('import_manhua')
                ->alias('m')
                ->whereExists(function ($query) use ($tableTrans) {
                    $query->table($tableTrans)
                        ->whereRaw("{$tableTrans}.manhua_id = m.id")
                        ->where('is_converted', 0);
                })
                ->column('id');

            $where[] = !empty($ids) ? ['id', 'in', $ids] : ['id', '=', 0];
        }

        // 分页统计
        $total = $this->model->where($where)->count();

        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($param['page'], $param['limit'])
            ->select();

        // 查询章节统计
        foreach ($list as &$v) {
            $v['chapter_count'] = Db::name('import_capter')->where('manhua_id', $v['id'])->count();
            $v['converted_chapter_count'] = Db::name('import_capter')->where('manhua_id', $v['id'])->where('is_converted', 1)->count();

            // 获取 ticai 名称
            $v['ticai_name'] = $v['ticai_id']
                ? Db::name('ticai')->where('id', $v['ticai_id'])->value('name')
                : '';
        }

        $img_domain = Configs::get('IMAGE_HOST');

        $page = $param['page'];
        $limit = $param['limit'];
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'param' => $param,
            'img_domain' => $img_domain,
        ]);

        return $this->fetch();
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = request()->post();
            $manhuaId = $param['id'] ?? 0;

            // 格式化 tags
            $param['tags'] = !empty($param['tags']) && is_array($param['tags'])
                ? implode(',', $param['tags'])
                : ($param['tags'] ?? '');

            // ✅ 保存主表（中文 zh）
            $mainData = [
                'title' => $param['title'] ?? '',
                'desc' => $param['desc'] ?? '',
                'keyword' => $param['keyword'] ?? '',
                'author' => $param['author'] ?? '',
                'image' => $param['image'] ?? '',
                'cover' => $param['cover'] ?? '',
                'cover_horizontal' => $param['cover_horizontal'] ?? '',
                'tags' => $param['tags'],
                'ticai_id' => $param['ticai_id'],
                'mhstatus' => $param['mhstatus'] ?? 0,
                'status' => $param['status'] ?? 1,
                'age18' => $param['age18'] ?? 0,
                'project_visibility' => $param['project_visibility'] ?? 1,
            ];

            if ($manhuaId) {
                Db::name('import_manhua')->where('id', $manhuaId)->update($mainData);
            } else {
                $mainData['create_time'] = time();
                $manhuaId = Db::name('import_manhua')->insertGetId($mainData);
            }

            // ✅ 保存多语言翻译（参考 ComicController）
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {

                    // 中文 zh 更新主表
                    if ($lang === 'zh') {
                        $zhData = [
                            'id' => $manhuaId,
                            'title' => $trans['title'] ?? '',
                            'desc' => $trans['desc'] ?? '',
                            'keyword' => $trans['keyword'] ?? '',
                            'image' => $trans['image'] ?? '',
                            'cover' => $trans['cover'] ?? '',
                            'cover_horizontal' => $trans['cover_horizontal'] ?? '',
                        ];
                        Db::name('import_manhua')->where('id', $manhuaId)->update($zhData);
                        continue;
                    }

                    // 其它语言 → insert or update import_manhua_trans
                    $transData = [
                        'manhua_id' => $manhuaId,
                        'lang_code' => $lang,
                        'title' => $trans['title'] ?? '',
                        'desc' => $trans['desc'] ?? '',
                        'keyword' => $trans['keyword'] ?? '',
                        'image' => $trans['image'] ?? '',
                        'cover' => $trans['cover'] ?? '',
                        'cover_horizontal' => $trans['cover_horizontal'] ?? '',
                    ];

                    $exists = Db::name('import_manhua_trans')
                        ->where('manhua_id', $manhuaId)
                        ->where('lang_code', $lang)
                        ->find();

                    if ($exists) {
                        Db::name('import_manhua_trans')->where('id', $exists['id'])->update($transData);
                    } else {
                        Db::name('import_manhua_trans')->insert($transData);
                    }
                }
            }

            return json(['code' => 1, 'msg' => '保存成功']);
        }

        // ========= GET 请求 =========
        $id = input('id');
        $info = Db::name('import_manhua')->where('id', $id)->find();

        // ✅ 获取所有翻译
        $translations = Db::name('import_manhua_trans')
            ->where('manhua_id', $id)
            ->select();

        // ✅ 转为以 lang_code 为 key 的结构
        $translationsByLang = [];
        foreach ($translations as $row) {
            $translationsByLang[$row['lang_code']] = $row;
        }

        // ✅ 拼入中文（主语言）
        $zhTranslation = [
            'lang_code' => 'zh',
            'title' => $info['title'] ?? '',
            'desc' => $info['desc'] ?? '',
            'keyword' => $info['keyword'] ?? '',
            'image' => $info['image'] ?? '',
            'cover' => $info['cover'] ?? '',
            'cover_horizontal' => $info['cover_horizontal'] ?? '',
        ];

        // ✅ 整合语言（所有存在于 import_manhua_trans 中的语言）
        $allLangs = Db::name('import_manhua_trans')
            ->distinct(true)
            ->column('lang_code');

        $langList = [];
        foreach ($allLangs as $code) {
            if ($code !== 'zh') {
                $langList[] = ['lang_code' => $code];
            }
        }

        // ✅ 自动补齐缺失语言项
        foreach ($langList as $lang) {
            $code = $lang['lang_code'];
            if (!isset($translationsByLang[$code])) {
                $translationsByLang[$code] = [
                    'lang_code' => $code,
                    'title' => '',
                    'desc' => '',
                    'keyword' => '',
                    'image' => '',
                    'cover' => '',
                    'cover_horizontal' => '',
                ];
            }
        }

        // ✅ 把 zh 放到最前面
        $translationsByLang = array_merge(['zh' => $zhTranslation], $translationsByLang);

        // 图片域名
        $img_domain = Configs::get('IMAGE_HOST');

        $selectedTags = [];
        if (!empty($info['tags'])) {
            $tagIds = explode(',', $info['tags']);
            $selectedTags = Db::table('tags')
                ->whereIn('id', $tagIds)
                ->field('id as value, name')
                ->select();
        }

        $this->assign([
            'info' => $info,
            'translations' => $translationsByLang,
            'img_domain' => $img_domain,
            'langList' => $langList,
            'selectedTags' => $selectedTags,
        ]);

        return $this->fetch();
    }


    /**
     * 修改状态 / 进度
     */
    public function modify()
    {
        $post = request()->post();
        if (empty($post['id']) || empty($post['field'])) {
            $this->error('参数错误');
        }
        $res = $this->model->where('id', $post['id'])->update([$post['field'] => $post['value']]);
        if ($res !== false)
            $this->success('保存成功');
        $this->error('保存失败');
    }

    /**
     * 删除漫画
     */
    public function del()
    {
        $id = input('param.id');
        try {
            $capterIds = db('import_capter')->where('manhua_id', $id)->column('id');
            if (!empty($capterIds)) {
                db('import_capter_trans')->whereIn('capter_id', $capterIds)->delete();
            }

            db('import_capter')->where('manhua_id', $id)->delete();

            db('import_manhua_trans')->where('manhua_id', $id)->delete();

            db('import_manhua')->where('id', $id)->delete();

            return json(['code' => 1, 'msg' => '删除成功']);
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '删除失败: ' . $e->getMessage()]);
        }
    }

    /**************************
     * 章节管理
     **************************/

    /**
     * 章节列表
     */
    public function chapter()
    {
        $comic_id = input("param.comic_id/d", 0);
        if (!$comic_id) {
            $this->error('漫画ID不存在');
        }

        $param = input();
        $param['page'] = !empty($param['page']) ? (int) $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? (int) $param['limit'] : 20;

        // 搜索条件
        $where = [['manhua_id', '=', $comic_id]];

        if (!empty($param['id'])) {
            $where[] = ['id', '=', trim($param['id'])];
        }

        if (!empty($param['title'])) {
            $t = trim($param['title']);
            $where[] = ['title', 'like', "%{$t}%"];
        }

        if (in_array($param['isvip'] ?? '', ['0', '1'], true)) {
            $where[] = ['isvip', '=', $param['isvip']];
        }

        // ───────────────────────────────────────────
        //  查询是否存在未转换的翻译内容
        // ───────────────────────────────────────────
        if (isset($param['untranslated']) && $param['untranslated'] == 1) {
            $prefix = config('database.prefix');
            $tableTrans = $prefix . 'import_capter_trans';

            $ids = Db::name('import_capter')
                ->alias('c')
                ->whereExists(function ($query) use ($tableTrans) {
                    $query->table($tableTrans)
                        ->whereRaw("{$tableTrans}.capter_id = c.id")
                        ->where('is_converted', 0);
                })
                ->column('id');

            $where[] = !empty($ids) ? ['id', 'in', $ids] : ['id', '=', 0];
        }

        // 排序
        $order = 'sort asc, id desc';
        if (!empty($param['order'])) {
            if ($param['order'] == 'title_asc')
                $order = 'title asc';
            elseif ($param['order'] == 'title_desc')
                $order = 'title desc';
            elseif ($param['order'] == 'sort_desc')
                $order = 'sort desc';
        }

        // 查询
        $query = Db::name('import_capter');
        $total = $query->where($where)->count();

        $list = $query->where($where)
            ->order($order)
            ->page($param['page'], $param['limit'])
            ->select();

        // --------------------------------------------------------
        // ⭐ 为每个章节附加：未转换翻译数 trans_unconverted_count
        // --------------------------------------------------------
        $prefix = config('database.prefix');
        $tableTrans = $prefix . 'import_capter_trans';

        foreach ($list as &$vo) {
            $vo['trans_unconverted_count'] = Db::table($tableTrans)
                ->where('capter_id', $vo['id'])
                ->where('is_converted', 0)
                ->count();
        }
        unset($vo);

        // 当前页与 limit
        $page = $param['page'];
        $limit = $param['limit'];

        // 模板用 URL 占位符
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';

        // 判断主漫画是否已经转换成功
        $import = Db::name('import_manhua')->where('id', $comic_id)->find();
        $manhua_converted = false;
        if (!empty($import['converted_id'])) {
            $manhua = Db::name('manhua')->where('id', $import['converted_id'])->find();
            if ($manhua) {
                $manhua_converted = true;
            }
        }

        // 图片域名
        $img_domain = Configs::get('IMAGE_HOST');

        // 模板变量
        $this->assign([
            'list' => $list,
            'total' => $total,
            'comic_id' => $comic_id,
            'param' => $param,
            'page' => $page,
            'limit' => $limit,
            'manhua_converted' => $manhua_converted,
            'img_domain' => $img_domain,
        ]);

        return $this->fetch();
    }


    /**
     * 添加/编辑章节
     */
    public function chapter_edit()
    {
        $id = input('get.id/d', 0);
        $comic_id = input('get.comic_id/d');

        if ($id) {
            $data = $this->chapterModel->where('id', $id)->find();
            if (!$data)
                $this->error('章节不存在');
        } else {
            $data = ['manhua_id' => $comic_id];
        }

        if (request()->isPost()) {
            $post = input('post.');
            $post['create_time'] = time();

            if ($id) {
                $this->chapterModel->where('id', $id)->update($post);
                $this->success('保存成功');
            } else {
                $this->chapterModel->insert($post);
                $this->success('添加成功');
            }
        }

        $this->assign('data', $data);
        return $this->fetch('chapter_edit'); // 对应 import/chapter_edit.html
    }

    /**
     * 删除章节
     */
    public function chapter_del()
    {
        $id = input('post.id/d');
        if (!$id)
            return json(['code' => 0, 'msg' => 'ID不能为空']);

        $res = $this->chapterModel->where('id', $id)->delete();
        if ($res)
            return json(['code' => 1, 'msg' => '删除成功']);
        return json(['code' => 0, 'msg' => '删除失败']);
    }


    public function chapter_info()
    {
        if (request()->isPost()) {
            $param = request()->post();
            $id = $param['id'] ?? 0;

            // ✅ 确保创建时间存在
            $param['create_time'] = $param['create_time'] ?? time();

            // ✅ zh 主语言保存到主表
            $zhTrans = $param['translations']['zh'] ?? [];

            $mainData = [
                'manhua_id' => $param['manhua_id'] ?? 0,
                'external_id' => $param['external_id'] ?? '',
                'title' => $zhTrans['title'] ?? '',
                'image' => $param['image'] ?? '',
                'imagelist' => $zhTrans['imagelist'] ?? '',
                'sort' => $param['sort'] ?? 0,
                'isvip' => $param['isvip'] ?? 0,
                'score' => $param['score'] ?? 0,
                'create_time' => $param['create_time'],
            ];

            // ✅ 主表保存（zh）
            if ($id) {
                Db::name('import_capter')->where('id', $id)->update($mainData);
            } else {
                $id = Db::name('import_capter')->insertGetId($mainData);
            }

            // ✅ 多语言保存（非 zh）
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {
                    if ($lang === 'zh')
                        continue;

                    $transData = [
                        'capter_id' => $id,
                        'lang_code' => $lang,
                        'title' => $trans['title'] ?? '',
                        'imagelist' => $trans['imagelist'] ?? '',
                    ];

                    $exists = Db::name('import_capter_trans')
                        ->where('capter_id', $id)
                        ->where('lang_code', $lang)
                        ->find();

                    if ($exists) {
                        Db::name('import_capter_trans')->where('id', $exists['id'])->update($transData);
                    } else {
                        Db::name('import_capter_trans')->insert($transData);
                    }
                }
            }

            return json(['code' => 1, 'msg' => '保存成功']);
        }

        // ✅ GET 请求：加载编辑页面
        $id = input('id');
        $info = [];

        if ($id) {
            $info = Db::name('import_capter')->where('id', $id)->find();
        }

        if (!$info) {
            $info = [
                'id' => '',
                'manhua_id' => input('manhua_id', 0),
                'external_id' => '',
                'title' => '',
                'image' => '',
                'imagelist' => '',
                'sort' => 0,
                'isvip' => 0,
                'score' => 0,
                'create_time' => time(),
            ];
        }

        // ✅ 获取多语言翻译表
        $translations = Db::name('import_capter_trans')
            ->where('capter_id', $id)
            ->select();

        $translationsByLang = [];
        foreach ($translations as $row) {
            $translationsByLang[$row['lang_code']] = $row;
        }

        // ✅ 拼上主语言 zh
        $translationsByLang = array_merge([
            'zh' => [
                'lang_code' => 'zh',
                'title' => $info['title'] ?? '',
                'imagelist' => $info['imagelist'] ?? '',
            ]
        ], $translationsByLang);

        // ✅ 支持语言（目前仅英文）
        $langList = [
            ['lang_code' => 'en'],
        ];

        $this->assign([
            'info' => $info,
            'translations' => $translationsByLang,
            'langList' => $langList,
        ]);

        return $this->fetch();
    }

    /**
     * 转换单个漫画及其章节、翻译
     */
    public function convert($id)
    {
        $newId = $this->importService->convertManhua($id);
        if (!$newId) {
            return json(['code' => 0, 'msg' => '转换失败或该漫画已转换']);
        }

        // 转换章节
        $chapters = Db::name('import_capter')->where('manhua_id', $id)->select();
        foreach ($chapters as $chapter) {
            $this->importService->convertChapter($chapter['id'], $newId);
        }

        $this->importService->updateLastChapter($newId);

        return json(['code' => 1, 'msg' => '转换成功', 'new_id' => $newId]);
    }

    public function convert_all()
    {
        try {
            // 推送队列任务，将当前类和方法名传给 Job
            Queue::later(0, \app\index\job\ConvertImportManhuaJob::class, [
                'service' => ImportManhuaService::class,
                'method' => 'convertAll'
            ]);

            return json([
                'code' => 1,
                'msg' => '转换任务已加入队列，后台正在执行...'
            ]);
        } catch (\Exception $e) {
            Log::error("队列推送失败: " . $e->getMessage());
            return json([
                'code' => 0,
                'msg' => '队列推送失败，请稍后重试'
            ]);
        }
    }

    /**
     * 转换所有未转换漫画及章节(暂不使用)
     */
    public function convert_all_old()
    {
        $convertedManhuaCount = 0;
        $convertedChapterCount = 0;

        // ===== 1️⃣ 转换漫画 =====
        $imports = Db::name('import_manhua')->where('is_converted', 0)->select();
        foreach ($imports as $import) {
            try {
                $newManhuaId = $this->importService->convertManhua($import['id']);
                if ($newManhuaId) {
                    $convertedManhuaCount++;
                }
            } catch (\Exception $e) {
                Log::error("漫画转换失败: {$import['id']} - " . $e->getMessage());
            }
        }

        // ===== 2️⃣ 转换章节 =====
        $prefix = config('database.prefix');
        $tableCapterTrans = $prefix . 'import_capter_trans';

        // 获取所有漫画（无论是否转换）
        $allImports = Db::name('import_manhua')
            ->where('is_converted', 1)
            ->select();

        foreach ($allImports as $import) {
            // 章节必须关联到已转换漫画
            $newManhuaId = $import['converted_id'] ?? 0;
            if (!$newManhuaId) {
                continue;
            }

            // 查询章节或翻译未转换的
            $chapters = Db::name('import_capter')
                ->alias('c')
                ->where('c.manhua_id', $import['id'])
                ->whereExists(function ($query) use ($tableCapterTrans) {
                    $query->table($tableCapterTrans)
                        ->whereRaw("{$tableCapterTrans}.capter_id = c.id")
                        ->where("{$tableCapterTrans}.is_converted", 0);
                })
                ->field('c.id')
                ->select();

            foreach ($chapters as $chapter) {
                try {
                    if ($this->importService->convertChapter($chapter['id'], $newManhuaId)) {
                        $convertedChapterCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("章节转换失败: {$chapter['id']} - " . $e->getMessage());
                }
            }
        }

        return json([
            'code' => 1,
            'msg' => "转换完成：漫画 {$convertedManhuaCount} 个，章节 {$convertedChapterCount} 个"
        ]);
    }

    /**
     * 转换漫画下所有章节
     */
    public function convert_chapter()
    {
        $importId = input('post.id/d', 0);

        if (!$importId) {
            return json(['code' => 0, 'msg' => '缺少漫画ID']);
        }

        $import = Db::name('import_manhua')
            ->where('id', $importId)
            ->where('is_converted', 1)
            ->find();

        if (!$import || empty($import['converted_id'])) {
            return json(['code' => 0, 'msg' => '请先转换主漫画']);
        }

        $manhuaId = $import['converted_id'];
        $prefix = config('database.prefix');
        $tableTrans = $prefix . 'import_capter_trans';

        // 查所有未转换章节
        $chapters = Db::name('import_capter')
            ->alias('c')
            ->leftJoin($tableTrans . ' t', 't.capter_id = c.id')
            ->where('c.manhua_id', $importId)
            ->where(function ($query) {
                $query->where('c.is_converted', 0)
                    ->whereOr('t.is_converted', 0);
            })
            ->order('c.sort asc, c.id asc')
            ->column('c.id');

        $count = 0;
        foreach ($chapters as $chapterId) {
            if ($this->importService->convertChapter($chapterId, $manhuaId)) {
                $count++;
            }
        }

        // 🔥 必须更新最新章节
        if ($count > 0) {
            $this->importService->updateLastChapter($manhuaId);
        }

        if ($count === 0) {
            return json(['code' => 0, 'msg' => '没有新的章节被转换']);
        }

        return json(['code' => 1, 'msg' => "章节转换完成，共导入 {$count} 个章节"]);
    }

    /**
     * 转换单个章节
     */
    public function convert_single_chapter()
    {
        $id = input('post.id/d', 0);
        if (!$id)
            return json(['code' => 0, 'msg' => '缺少章节ID']);

        $chapter = Db::name('import_capter')->where('id', $id)->find();
        if (!$chapter)
            return json(['code' => 0, 'msg' => '章节不存在']);

        // 如果章节已转换，检查翻译是否未转换
        if ($chapter['is_converted']) {
            $transUnconverted = Db::name('import_capter_trans')
                ->where('capter_id', $id)
                ->where('is_converted', 0)
                ->count();

            if ($transUnconverted == 0) {
                return json(['code' => 0, 'msg' => '该章节已转换']);
            }
        }

        $manhua = Db::name('import_manhua')->where('id', $chapter['manhua_id'])->find();
        if (empty($manhua['converted_id']))
            return json(['code' => 0, 'msg' => '请先转换主漫画']);

        $newId = $this->importService->convertChapter($id, $manhua['converted_id']);
        if (!$newId)
            return json(['code' => 0, 'msg' => '章节转换失败']);

        // 🔥 单章也必须更新最新章节
        $this->importService->updateLastChapter($manhua['converted_id']);

        return json(['code' => 1, 'msg' => '章节转换成功']);
    }

    /**
     * 重新处理导入图片（统一推送队列任务）
     *
     * 覆盖 import_manhua / import_manhua_trans / import_capter / import_capter_trans 四个表
     * 自动识别未下载/未转换的记录，逐条 push ImportManhuaImageJob 到队列
     */
    public function rehandle_images()
    {
        set_time_limit(0);

        $connection = '';
        $totalPushed = 0;

        try {
            // ========== 1️⃣ 处理漫画主表 import_manhua ==========
            $manhuaList = Db::name('import_manhua')
                ->where(function ($query) {
                    $query->where('image', 'like', '%http%')
                        ->whereOr('cover', 'like', '%http%')
                        ->whereOr('cover_horizontal', 'like', '%http%');
                })
                ->where('is_converted', 0)
                ->field('id')
                ->select();

            foreach ($manhuaList as $row) {
                Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                    'type' => 'manhua',
                    'id' => $row['id'],
                ], 'import_image');
                $totalPushed++;
            }

            // ========== 2️⃣ 处理漫画多语翻译表 import_manhua_trans ==========
            $transList = Db::name('import_manhua_trans')
                ->where(function ($query) {
                    $query->where('image', 'like', '%http%')
                        ->whereOr('cover', 'like', '%http%')
                        ->whereOr('cover_horizontal', 'like', '%http%');
                })
                ->where('is_converted', 0)
                ->field('manhua_id')
                ->group('manhua_id')
                ->select();

            foreach ($transList as $row) {
                Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                    'type' => 'manhua',
                    'id' => $row['manhua_id'],
                ], 'import_image');
                $totalPushed++;
            }

            // ========== 3️⃣ 处理章节主表 import_capter ==========
            $chapterList = Db::name('import_capter')
                ->where(function ($query) {
                    $query->where('image', 'like', '%http%')
                        ->whereOr('imagelist', 'like', '%http%');
                })
                ->where('is_converted', 0)
                ->field('id')
                ->select();

            foreach ($chapterList as $row) {
                Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                    'type' => 'chapter',
                    'id' => $row['id'],
                ], 'import_image');
                $totalPushed++;
            }

            // ========== 4️⃣ 处理章节翻译表 import_capter_trans ==========
            $chapterTransList = Db::name('import_capter_trans')
                ->where('imagelist', 'like', '%http%')
                ->where('is_converted', 0)
                ->field('capter_id')
                ->group('capter_id')
                ->select();

            foreach ($chapterTransList as $row) {
                Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                    'type' => 'chapter',
                    'id' => $row['capter_id'],
                ], 'import_image');
                $totalPushed++;
            }

            Log::info("[rehandleImages] 成功推送 {$totalPushed} 条导入任务到队列(import_image)");
            return json(['code' => 1, 'msg' => "已推送 {$totalPushed} 条导入任务"]);
        } catch (\Throwable $e) {
            Log::error("[rehandleImages] 执行失败: " . $e->getMessage());
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * 加载章节图片内容
     */
    public function photos()
    {
        $id = input("param.id");
        $lang = input("param.lang", "zh");

        if ($lang === 'zh') {
            // 中文从主表获取
            $images = $this->chapterModel->where('id', '=', $id)->value('imagelist');
        } else {
            // 其他语言从翻译表获取
            $images = Db::name('import_capter_trans')
                ->where('capter_id', '=', $id)
                ->where('lang_code', '=', $lang)
                ->value('imagelist');
        }

        $data = [];
        if (!empty($images)) {
            $pic_url = Configs::get("IMAGE_HOST");
            $csv = $this->imageListToCsv($images);

            $arr = explode(',', $csv);
            foreach ($arr as $v) {
                $data[]['src'] = $pic_url . $v;
            }
        }
        $result["data"] = $data;
        $result["code"] = 1;
        return json($result);
    }

    private function imageListToCsv(string $images): string
    {
        $arr = json_decode($images, true);

        if (is_array($arr)) {
            return implode(',', $arr);
        }

        return $images;
    }

}
