<?php
namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\facade\Log;
use think\Queue;
use app\traits\HandlesImageUpload;
use app\index\model\Ticai as TicaiModel;
use app\index\model\Tags as TagsModel;
class Import extends Controller
{

    use HandlesImageUpload;

    /**
     * ✅ 通用签名验证（用于所有导入接口）
     *
     * @param array $data POST 数据
     * @param string $defaultKey 默认 key（如 listManhua 或 manhua_id）
     * @return bool
     */
    private function checkAuth(array $data, string $defaultKey = ''): bool
    {
        $secret = config('import.import_secret');
        $expire = intval(config('import.token_expire'));

        $key = $data['key'] ?? $defaultKey;
        $timestamp = intval($data['timestamp'] ?? 0);
        $sign = $data['sign'] ?? '';

        if (abs(time() - $timestamp) > $expire) {
            return false;
        }

        $localSign = md5($key . $timestamp . $secret);
        return hash_equals($localSign, $sign);
    }

    /**
     * 🟩 創建漫畫
     */
    public function createManhua(Request $request)
    {

        $data = $request->post();

        if (!$this->checkAuth($data, 'createManhua')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $externalId = trim($data['external_id'] ?? '');
        if (empty($externalId)) {
            return showPlain(0, '缺少 external_id');
        }

        $exists = Db::name('import_manhua')->where('external_id', $externalId)->find();
        if ($exists) {
            return showPlain(0, 'external_id 已存在');
        }

        $insert = [
            'external_id' => $externalId,
            'title' => $data['title'] ?? '',
            'desc' => $data['desc'] ?? '',
            'keyword' => $data['keyword'] ?? '',
            'author' => $data['author'] ?? '',
            'image' => $data['image'] ?? '',
            'cover' => $data['cover'] ?? '',
            'cover_horizontal' => $data['cover_horizontal'] ?? '',
            'ticai_id' => 0,
            'tags' => '',
            'mhstatus' => $data['mhstatus'] ?? 0,
            'status' => 1,
            'age18' => $data['age18'] ?? 1,
            'project_visibility' => 1,
            'from_source' => $data['from_source'] ?? 'api',
            'create_time' => time(),
        ];

        $ticaiName = trim($data['ticai'] ?? '');
        if (!empty($ticaiName)) {
            $insert['ticai_id'] = $this->findOrCreateTicai($ticaiName);
        }

        $tagsStr = trim($data['tags'] ?? '');
        if (!empty($tagsStr)) {
            $insert['tags'] = $this->findOrCreateTags($tagsStr);
        }

        $manhuaId = Db::name('import_manhua')->insertGetId($insert);

        // 翻譯內容
        if (!empty($data['trans']) && is_array($data['trans'])) {
            $trans = $this->filterTrans($data['trans'] ?? []);
            foreach ($trans as $lang => $t) {
                Db::name('import_manhua_trans')->insert([
                    'manhua_id' => $manhuaId,
                    'lang_code' => $lang,
                    'title' => $t['title'] ?? '',
                    'desc' => $t['desc'] ?? '',
                    'keyword' => $t['keyword'] ?? '',
                    'cover' => $t['cover'] ?? '',
                    'cover_horizontal' => $t['cover_horizontal'] ?? '',
                    'image' => $t['image'] ?? '',
                ]);
            }
        }

        Queue::push(\app\index\job\ImportManhuaImageJob::class, [
            'type' => 'manhua',
            'id' => $manhuaId,
        ], 'import_image');

        return showPlain(1, ['manhua_id' => $manhuaId]);
    }

    /**
     * 🟨 創建章節
     */
    public function createChapter(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'createChapter')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        // 支持 external_id 或 manhua_id
        $manhuaId = intval($data['manhua_id'] ?? 0);
        $externalId = trim($data['manhua_external_id'] ?? '');

        if (!$manhuaId && !$externalId) {
            return showPlain(0, '缺少 manhua_id 或 manhua_external_id');
        }

        // ✅ 统一验证漫画存在性
        if ($manhuaId) {
            $manhua = Db::name('import_manhua')->where('id', $manhuaId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在（manhua_id 无效）');
            }
        } elseif ($externalId) {
            $manhua = Db::name('import_manhua')->where('external_id', $externalId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在（manhua_external_id 无效）');
            }
            $manhuaId = $manhua['id'];
        }

        // 检查章节 external_id
        $externalChapterId = trim($data['external_id'] ?? '');
        if (empty($externalChapterId)) {
            return showPlain(0, 'external_id 参数不能为空');
        }

        // 检查是否重复章节
        $existsChapter = Db::name('import_capter')
            ->where('external_id', $externalChapterId)
            ->where('manhua_id', $manhuaId)
            ->find();

        if ($existsChapter) {
            return showPlain(0, ['id' => $existsChapter['id'], 'msg' => '章节已存在，跳过']);
        }

        // if (empty($data['imagelist'])) {
        //     return showPlain(0, 'imagelist 为空，章节未创建');
        // }

        // 创建章节
        $chapterId = Db::name('import_capter')->insertGetId([
            'manhua_id' => $manhuaId,
            'external_id' => $externalChapterId,
            'title' => $data['title'] ?? '',
            'image' => $data['image'] ?? '',
            'imagelist' => isset($data['imagelist'])
                ? (is_array($data['imagelist']) ? json_encode($data['imagelist'], JSON_UNESCAPED_SLASHES) : $data['imagelist'])
                : '',
            'sort' => intval($data['sort'] ?? 0),
            'isvip' => intval($data['isvip'] ?? 0),
            'score' => intval($data['score'] ?? 0),
            'create_time' => time(),
        ]);

        // 翻译处理
        if (!empty($data['trans'])) {
            $trans = $this->filterTrans($data['trans'] ?? []);
            foreach ($trans as $lang => $t) {

                if (empty($t['title']) || empty($t['imagelist'])) {
                    continue;
                }

                Db::name('import_capter_trans')->insert([
                    'capter_id' => $chapterId,
                    'lang_code' => $lang,
                    'title' => $t['title'] ?? '',
                    'imagelist' => isset($t['imagelist'])
                        ? json_encode($t['imagelist'], JSON_UNESCAPED_SLASHES)
                        : '',
                ]);
            }
        }

        Queue::push(\app\index\job\ImportManhuaImageJob::class, [
            'type' => 'chapter',
            'id' => $chapterId,
        ], 'import_image');

        return showPlain(1, ['chapter_id' => $chapterId]);
    }

    /**
     * 🟦 創建角色
     */
    public function createActor(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'createActor')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        // 支持 external_id 或 manhua_id
        $manhuaId = intval($data['manhua_id'] ?? 0);
        $externalManhuaId = trim($data['manhua_external_id'] ?? '');

        if (!$manhuaId && !$externalManhuaId) {
            return showPlain(0, '缺少 manhua_id 或 manhua_external_id');
        }

        // ✅ 统一检查 import_manhua 是否存在
        if ($manhuaId) {
            $manhua = Db::name('import_manhua')->where('id', $manhuaId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在（manhua_id 无效）');
            }
        } else {
            $manhua = Db::name('import_manhua')->where('external_id', $externalManhuaId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在（manhua_external_id 无效）');
            }
            $manhuaId = $manhua['id'];
        }

        // 检查 external_id
        $externalActorId = trim($data['external_id'] ?? '');
        if (empty($externalActorId)) {
            return showPlain(0, '缺少 external_id');
        }

        // 检查是否已存在
        $exists = Db::name('import_manhua_actors')
            ->where('external_id', $externalActorId)
            ->find();

        if ($exists) {
            return showPlain(0, ['id' => $exists['id'], 'msg' => '角色已存在']);
        }

        // 插入角色
        $actorId = Db::name('import_manhua_actors')->insertGetId([
            'manhua_id' => $manhuaId,
            'external_id' => $externalActorId,
            'name' => $data['name'] ?? '',
            'img' => $data['img'] ?? '',
            'create_time' => time(),
        ]);

        // 翻译处理
        if (!empty($data['trans']) && is_array($data['trans'])) {
            $trans = $this->filterTrans($data['trans'] ?? []);
            foreach ($trans as $lang => $name) {
                Db::name('import_manhua_actor_trans')->insert([
                    'actor_id' => $actorId,
                    'lang_code' => $lang,
                    'name' => $name,
                ]);
            }
        }

        // 入队图片任务
        Queue::push(\app\index\job\ImportManhuaImageJob::class, [
            'type' => 'actor',
            'id' => $actorId,
        ], 'import_image');

        return showPlain(1, ['actor_id' => $actorId]);
    }

    /** 🟩 更新漫画 */
    public function updateManhua(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'updateManhua')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $manhuaId = intval($data['manhua_id'] ?? 0);
        $externalId = trim($data['external_id'] ?? '');
        $force = intval($data['force_update'] ?? 0);

        if (!$manhuaId && !$externalId) {
            return showPlain(0, '缺少 manhua_id 或 external_id');
        }

        $query = Db::name('import_manhua');
        if ($manhuaId) {
            $query->where('id', $manhuaId);
        } else {
            $query->where('external_id', $externalId);
        }

        $manhua = $query->find();
        if (!$manhua) {
            return showPlain(0, '漫画不存在');
        }

        if ($manhua['is_converted'] == 1 && !$force) {
            return showPlain(0, '漫画已转换（is_converted=1），禁止修改');
        }

        $update = [];
        $fields = ['title', 'desc', 'keyword', 'author', 'image', 'cover', 'cover_horizontal', 'mhstatus', 'age18', 'from_source'];

        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $update[$f] = $data[$f];
            }
        }

        // ✅ 自动处理题材
        if (!empty($data['ticai'])) {
            $update['ticai_id'] = $this->findOrCreateTicai($data['ticai']);
        }

        // ✅ 自动处理标签
        if (!empty($data['tags'])) {
            $update['tags'] = $this->findOrCreateTags($data['tags']);
        }


        if (empty($update)) {
            return showPlain(0, '没有可更新字段');
        }

        Db::name('import_manhua')->where('id', $manhua['id'])->update($update);

        // 翻译更新
        if (!empty($data['trans']) && is_array($data['trans'])) {
            $trans = $this->filterTrans($data['trans']);
            foreach ($trans as $lang => $t) {
                $exists = Db::name('import_manhua_trans')
                    ->where('manhua_id', $manhua['id'])
                    ->where('lang_code', $lang)
                    ->find();

                $transData = [
                    'title' => $t['title'] ?? '',
                    'desc' => $t['desc'] ?? '',
                    'keyword' => $t['keyword'] ?? '',
                    'cover' => $t['cover'] ?? '',
                    'cover_horizontal' => $t['cover_horizontal'] ?? '',
                    'image' => $t['image'] ?? '',
                ];

                if ($exists) {
                    Db::name('import_manhua_trans')
                        ->where('id', $exists['id'])
                        ->update($transData);
                } else {
                    $transData['manhua_id'] = $manhua['id'];
                    $transData['lang_code'] = $lang;
                    Db::name('import_manhua_trans')->insert($transData);
                }
            }
        }

        if ($this->fieldChanged($manhua, $update, ['image', 'cover', 'cover_horizontal'])) {
            Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                'type' => 'manhua',
                'id' => $manhua['id'],
            ], 'import_image');
        }


        return showPlain(1, ['msg' => '漫画更新成功']);
    }



    public function updateChapter(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'updateChapter')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $chapterId = intval($data['chapter_id'] ?? 0);
        $externalId = trim($data['external_id'] ?? '');
        $force = intval($data['force_update'] ?? 0);

        if (!$chapterId && !$externalId) {
            return showPlain(0, '缺少 chapter_id 或 external_id');
        }

        $query = Db::name('import_capter');
        if ($chapterId) {
            $query->where('id', $chapterId);
        } else {
            $query->where('external_id', $externalId);
        }

        $chapter = $query->find();
        if (!$chapter) {
            return showPlain(0, '章节不存在');
        }

        if ($chapter['is_converted'] == 1 && !$force) {
            return showPlain(0, '章节已转换（is_converted=1），禁止修改');
        }

        $update = [];
        $fields = ['title', 'image', 'imagelist', 'sort', 'isvip', 'score'];
        foreach ($fields as $f) {
            if (!isset($data[$f]))
                continue;

            if ($f === 'imagelist') {
                if (is_array($data[$f])) {
                    $update[$f] = json_encode($data[$f], JSON_UNESCAPED_SLASHES);
                } else {
                    $decoded = json_decode($data[$f], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $update[$f] = $data[$f];
                    } else {
                        $update[$f] = json_encode([$data[$f]], JSON_UNESCAPED_SLASHES);
                    }
                }
            } else {
                $update[$f] = $data[$f];
            }
        }

        if (empty($update)) {
            return showPlain(0, '没有可更新字段');
        }

        Db::name('import_capter')->where('id', $chapter['id'])->update($update);

        // 翻译更新
        if (!empty($data['trans']) && is_array($data['trans'])) {
            $trans = $this->filterTrans($data['trans']);
            foreach ($trans as $lang => $t) {
                $exists = Db::name('import_capter_trans')
                    ->where('capter_id', $chapter['id'])
                    ->where('lang_code', $lang)
                    ->find();

                $transData = [
                    'title' => $t['title'] ?? '',
                    'imagelist' => isset($t['imagelist'])
                        ? json_encode($t['imagelist'], JSON_UNESCAPED_SLASHES)
                        : '',
                ];

                if ($exists) {
                    Db::name('import_capter_trans')->where('id', $exists['id'])->update($transData);
                } else {
                    $transData['capter_id'] = $chapter['id'];
                    $transData['lang_code'] = $lang;
                    Db::name('import_capter_trans')->insert($transData);
                }
            }
        }

        // ⚙️ 如果图片字段有变化，重新入队
        if ($this->fieldChanged($chapter, $update, ['image', 'imagelist'])) {
            Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                'type' => 'chapter',
                'id' => $chapter['id'],
            ], 'import_image');
        }


        return showPlain(1, ['msg' => '章节更新成功']);
    }

    /** 🟦 更新角色 */
    public function updateActor(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'updateActor')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $actorId = intval($data['actor_id'] ?? 0);
        $externalId = trim($data['external_id'] ?? '');
        $force = intval($data['force_update'] ?? 0);

        if (!$actorId && !$externalId) {
            return showPlain(0, '缺少 actor_id 或 external_id');
        }

        $query = Db::name('import_manhua_actors');
        if ($actorId) {
            $query->where('id', $actorId);
        } else {
            $query->where('external_id', $externalId);
        }

        $actor = $query->find();
        if (!$actor) {
            return showPlain(0, '角色不存在');
        }

        if ($actor['is_converted'] == 1 && !$force) {
            return showPlain(0, '角色已转换（is_converted=1），禁止修改');
        }

        $update = [];
        if (isset($data['name']))
            $update['name'] = $data['name'];
        if (isset($data['img']))
            $update['img'] = $data['img'];
        if (empty($update)) {
            return showPlain(0, '没有可更新字段');
        }

        Db::name('import_manhua_actors')->where('id', $actor['id'])->update($update);

        // 翻译更新
        if (!empty($data['trans']) && is_array($data['trans'])) {
            $trans = $this->filterTrans($data['trans']);
            foreach ($trans as $lang => $name) {
                $exists = Db::name('import_manhua_actor_trans')
                    ->where('actor_id', $actor['id'])
                    ->where('lang_code', $lang)
                    ->find();

                if ($exists) {
                    Db::name('import_manhua_actor_trans')->where('id', $exists['id'])->update(['name' => $name]);
                } else {
                    Db::name('import_manhua_actor_trans')->insert([
                        'actor_id' => $actor['id'],
                        'lang_code' => $lang,
                        'name' => $name,
                    ]);
                }
            }
        }

        if ($this->fieldChanged($actor, $update, ['img'])) {
            Queue::push(\app\index\job\ImportManhuaImageJob::class, [
                'type' => 'manhua',
                'id' => $actor['id'],
            ], 'import_image');
        }


        return showPlain(1, ['msg' => '角色更新成功']);
    }


    /** ✅ 查询所有导入漫画（分页） */
    public function listManhua(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'listManhua')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $page = max(1, intval($data['page'] ?? 1));
        $limit = min(50, intval($data['limit'] ?? 20));

        $list = Db::name('import_manhua')->order('id desc')->page($page, $limit)->select();
        $total = Db::name('import_manhua')->count();
        $totalPage = (int) ceil($total / $limit);

        return showPlain(1, [
            'list' => $list,
            'current_page' => $page,
            'total_page' => $totalPage,
            'per_page' => $limit,
            'total' => $total,
        ]);
    }

    /** ✅ 查询漫画完整详情（含章节与角色） */
    /** 查询漫画完整详情（含章节与角色） */
    public function getManhua(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'getManhua')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $searchId = $data['manhua_id'] ?? null;
        $externalId = $data['manhua_external_id'] ?? null;

        if (!$searchId && !$externalId) {
            return showPlain(0, '缺少 manhua_id 或 external_id');
        }

        // 根据 external_id 或 id 查找
        $query = Db::name('import_manhua');
        if ($searchId) {
            $query->where('id', intval($searchId));
        } elseif ($externalId) {
            $query->where('external_id', strval($externalId));
        }

        $manhua = $query->find();
        if (!$manhua)
            return showPlain(0, '漫画不存在');

        $id = $manhua['id'];

        $trans = Db::name('import_manhua_trans')->where('manhua_id', $id)->select();
        $chapters = Db::name('import_capter')->where('manhua_id', $id)->order('sort asc')->select();
        $actors = Db::name('import_manhua_actors')->where('manhua_id', $id)->select();

        foreach ($chapters as &$c) {
            $c['trans'] = Db::name('import_capter_trans')->where('capter_id', $c['id'])->select();
        }

        foreach ($actors as &$a) {
            $a['trans'] = Db::name('import_manhua_actor_trans')->where('actor_id', $a['id'])->select();
        }

        return showPlain(1, [
            'manhua' => $manhua,
            'trans' => $trans,
            'chapters' => $chapters,
            'actors' => $actors,
        ]);
    }


    /** ✅ 查询章节列表 */
    public function listChapters(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'listChapters')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $id = intval($data['manhua_id'] ?? 0);
        $externalId = $data['manhua_external_id'] ?? null;

        // 如果没有 manhua_id，但提供了 external_id，就查主表获取 id
        if (!$id && $externalId) {
            $manhua = Db::name('import_manhua')->where('external_id', $externalId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在');
            }
            $id = $manhua['id'];
        }

        if (!$id) {
            return showPlain(0, '缺少 manhua_id 或 external_id');
        }

        $list = Db::name('import_capter')->where('manhua_id', $id)->order('sort asc')->select();

        foreach ($list as &$c) {
            $c['trans'] = Db::name('import_capter_trans')->where('capter_id', $c['id'])->select();
        }

        return showPlain(1, $list);
    }


    /** ✅ 查询角色列表 */
    public function listActors(Request $request)
    {
        $data = $request->post();

        if (!$this->checkAuth($data, 'listActors')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $id = intval($data['manhua_id'] ?? 0);
        $externalId = $data['manhua_external_id'] ?? null;

        if (!$id && $externalId) {
            $manhua = Db::name('import_manhua')->where('external_id', $externalId)->find();
            if (!$manhua) {
                return showPlain(0, '漫画不存在');
            }
            $id = $manhua['id'];
        }

        if (!$id) {
            return showPlain(0, '缺少 manhua_id 或 external_id');
        }

        $list = Db::name('import_manhua_actors')->where('manhua_id', $id)->select();

        foreach ($list as &$a) {
            $a['trans'] = Db::name('import_manhua_actor_trans')->where('actor_id', $a['id'])->select();
        }

        return showPlain(1, $list);
    }

    protected function filterTrans(array $trans): array
    {
        $allowedLangs = config('import.allowed_langs', ['en']);
        $result = [];
        foreach ($trans as $lang => $t) {
            if (in_array($lang, $allowedLangs)) {
                $result[$lang] = $t;
            } else {
                Log::warning("忽略不支持的语言: {$lang}");
            }
        }
        return $result;
    }

    private function fieldChanged(array $old, array $new, array $keys): bool
    {
        foreach ($keys as $k) {
            if (isset($new[$k]) && $old[$k] != $new[$k]) {
                return true;
            }
        }
        return false;
    }


    public function testRsync()
    {
        $prefix = 'test_image';

        // 调用上传方法
        $result = $this->saveImageFromUrl(
            'https://www.planetware.com/wpimages/2020/02/france-in-pictures-beautiful-places-to-photograph-eiffel-tower.jpg',
            'manhua',
            $prefix
        );

        return showPlain(1, [
            'saved_path' => $result
        ]);
    }

    public function ticai(Request $request)
    {
        $data = $request->post();
        if (!$this->checkAuth($data, 'ticai')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = TicaiModel::field('id,name')->where('switch', 1)->order('id asc')->select();
        return showPlain(1, $lists);
    }

    public function tags(Request $request)
    {
        $data = $request->post();
        if (!$this->checkAuth($data, 'tags')) {
            return showPlain(0, '未经授权（签名无效）');
        }

        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = TagsModel::field('id,name')->where('status', 1)->order('sort desc')->select();
        return showPlain(1, $lists);
    }

    /**
     * ✅ 根据题材名获取或创建题材
     */
    protected function findOrCreateTicai($name)
    {
        if (empty($name))
            return 0;

        $ticai = TicaiModel::where('name', $name)->find();
        if ($ticai) {
            $ticaiId = $ticai['id'];
        } else {
            $ticaiId = TicaiModel::insertGetId([
                'name' => $name,
                'switch' => 1,
                'type' => 1,
                'is_top' => 0,
                'translate_status' => 0,
            ]);
        }

        return $ticaiId;
    }

    /**
     * ✅ 根据标签字符串（逗号分隔）获取或创建标签
     */
    protected function findOrCreateTags($tagString)
    {
        if (empty($tagString))
            return '';

        $tags = explode(',', $tagString);
        $tagIds = [];

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName))
                continue;

            $tag = TagsModel::where('name', $tagName)->find();
            if ($tag) {
                $tagId = $tag['id'];
            } else {
                $tagId = TagsModel::insertGetId([
                    'name' => $tagName,
                    'sort' => 99,
                    'comic_count' => 0,
                    'status' => 1,
                    'is_top' => 0,
                    'translate_status' => 0,
                    'project_visibility' => 1,
                ]);
            }

            $tagIds[] = $tagId;
        }

        return implode(',', $tagIds);
    }
}
