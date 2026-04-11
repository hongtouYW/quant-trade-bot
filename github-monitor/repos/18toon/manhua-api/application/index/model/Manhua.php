<?php

namespace app\index\model;

use app\lib\exception\BaseException;
use app\traits\HasTranslation;
use app\traits\HasProjectVisibility;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Manhua extends Model
{
    use HasTranslation, HasProjectVisibility;


    const list_field = 'id,title,image,cover,auther,vipcanread,mhstatus,xianmian,issole,ticai_id,last_chapter_title,view,mark,subscribe,desc,age18,update_time';
    const info_field = 'id,title,tags,image,cover,auther,vipcanread,mhstatus,xianmian,issole,ticai_id,last_chapter_title,view,mark,subscribe,update_time,desc,age18';
    const inter_field = 'a.id,a.title,a.image,a.cover,a.auther,a.mhstatus,a.xianmian,a.issole,a.ticai_id,a.last_chapter_title,a.view,mark,a.subscribe,a.desc,a.age18,b.addtime';
    const list_tran_field = 'm.id,m.image,m.cover,m.auther,m.vipcanread,m.mhstatus,m.xianmian,m.isjingpin,m.issole,m.ticai_id,m.view,m.mark,m.subscribe,m.age18,m.update_time';
    const info_tran_field = 'm.id,m.tags,m.image,m.cover,m.auther,m.vipcanread,m.mhstatus,m.xianmian,m.issole,m.ticai_id,m.view,m.mark,m.subscribe,m.update_time,m.age18';
    const inter_tran_field = 'm.id,m.title,m.image,m.cover,m.auther,m.mhstatus,m.xianmian,m.issole,m.ticai_id,m.last_chapter_title,m.view,m.mark,m.subscribe,m.desc,m.age18,b.addtime';

    protected $append = ["ticai_name", "new_release"];

    public function getIsSubscribeAttr($val, $data)
    {
        $is_subscribe = 0;
        $count = Subscribe::where('member_id', '=', getUid())->where('manhua_id', '=', $data['id'])->where('status', '1')->count();
        if ($count) {
            $is_subscribe = 1;
        }
        return $is_subscribe;
    }

    public function getIsFavoriteAttr($val, $data)
    {
        $is_favorite = 0;
        $count = Favorites::where('member_id', '=', getUid())->where('manhua_id', '=', $data['id'])->where('status', '1')->count();
        if ($count) {
            $is_favorite = 1;
        }
        return $is_favorite;
    }

    public function getTagsAttr($val, $data)
    {
        $val = [];
        if (!empty($data['tags'])) {
            $val = Tags::field('id,name')->where('id', 'in', $data['tags'])->select();
        }
        return $val;
    }

    public function getNewReleaseAttr($value, $data)
    {
        // 默认不是新发布
        $is_new = 0;

        if (!empty($data['update_time'])) {
            // 计算当前时间和 update_time 的差
            $updateTime = $data['update_time'];
            $diff = time() - $updateTime;

            // 一天内算新发布
            if ($diff <= 259200) { // 24 * 60 * 60 * 3 (3天内)
                $is_new = 1;
            }
        }

        return $is_new;
    }

    public function chapter()
    {
        return $this->hasMany('Chapter', 'manhua_id', 'id');
    }

    public function ManhuaActors()
    {
        return $this->hasMany('ManhuaActor', 'manhua_id', 'id');
    }

    public function ManhuaHighlights()
    {
        return $this->hasMany('ManhuaHighlight', 'manhua_id', 'id');
    }

    public function getLastViewAttr($val, $data)
    {
        $capterTable = (new Chapter())->getFullTableName();
        $last = History::alias('a')->join([$capterTable => 'b'], 'b.id = a.capter_id', 'inner')->field('a.manhua_id,a.capter_id,b.title')->where('member_id', '=', getUid())->where('a.manhua_id', '=', $data['id'])->order('addtime desc')->find();
        return $last;
    }

    public function getTicaiNameAttr($value, $data)
    {
        $lang = getInput('lang') ?? "zh";

        $ticaiId = $data['ticai_id'] ?? null;

        if (!$ticaiId)
            return '';

        // 静态缓存，避免 N+1 查询
        static $ticaiMap = [];

        // 缓存 key 支持 lang
        $cacheKey = "{$lang}_{$ticaiId}";

        if (!isset($ticaiMap[$cacheKey])) {
            $query = Ticai::alias('c')->where('c.id', $ticaiId);

            // 联表翻译
            Ticai::withTranslation(
                $query,
                'c',
                $lang,
                ['name'],
                'qiswl_ticai_trans',
                'ticai_id',
            );

            $record = $query->find();
            $ticaiMap[$cacheKey] = $record['name'] ?? '';
        }

        return $ticaiMap[$cacheKey];
    }

    // public function getTicainameAttr($val, $data)
    // {
    //     return Ticai::where('id', '=', $data['ticai_id'])->value('name');
    // }

    public function getImageAttr($val)
    {
        if (!empty($val)) {
            // $image_url = Config::get('IMAGE_HOST');
            $image_url = '';
            $val = $image_url . $val;
        }
        return $val;
    }

    public function getCoverAttr($val)
    {
        if (!empty($val)) {
            // $image_url = Config::get('IMAGE_HOST');
            $image_url = '';
            $val = $image_url . $val;
        }
        return $val;
    }

    public static function getMid()
    {
        $mid = getInput('mid');
        $manhuaInfo = self::field('id,status')->where('id', '=', $mid)->find();

        if (!$manhuaInfo) {
            throw new BaseException(3001);
        }
        if ($manhuaInfo['status'] == 0) {
            throw new BaseException(3002);
        }
        return $manhuaInfo['id'];
    }

    public static function indexLists($page, $limit, $type, $date, $lang = 'zh')
    {
        $where[] = ['status', '=', '1'];
        $order = 'update_time desc';
        $redis_key = "comic_index_{$page}_{$limit}_{$type}_{$lang}";

        switch ($type) {
            case 1:
                if (!empty($date)) {
                    $start_time = strtotime($date . ' 00:00:00');
                    $end_time = strtotime($date . ' 23:59:59');
                    $where[] = ['update_time', 'between time', [$start_time, $end_time]];
                }
                $redis_key .= '_' . $date;
                break;
            case 2:
                $where[] = ['issole', '=', 1];
                break;
            case 3:
                $where[] = ['isjingpin', '=', 1];
                break;
            case 4:
                $where[] = ['xianmian', '=', 1];
                break;
            case 5:
                break;
            case 6:
                $where[] = ['xianmian', '=', 0];
                break;
        }

        $redis = new Redis();
        $results = $redis->get($redis_key);

        if (!$results) {
            $query = self::alias('m')
                ->field(self::list_tran_field)
                ->where($where)
                ->order($order);

            self::applyProjectVisibility($query, 'm');
            // 处理漫画和翻译
            self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

            $results = $query
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page,
                ])
                ->toArray();

            if (!empty($results['data'])) {
                self::appendMaxChapterSort($results['data']);

                // 封面图处理
                foreach ($results['data'] as &$comic) {
                    if (empty($comic['cover_horizontal']) && !empty($comic['image'])) {
                        $comic['cover_horizontal'] = $comic['image'];
                    }
                }
                unset($comic); // 避免引用问题

                $redis->set($redis_key, $results, 86400);
            }
        }

        // if (in_array($type, [1, 5]) && !empty($results['data'])) {
        //     shuffle($results['data']);
        // }

        return $results;
    }

    public static function rank($page, $limit, $type, $date, $lang)
    {
        $redis = new Redis();
        $redis_key = 'comic_lists_' . implode('_', [$page, $limit, $type, $date, $lang]);
        $results = $redis->get($redis_key);

        $order = 'update_time desc';
        if (!$results) {
            $query = self::alias('m')
                ->field(self::list_tran_field)
                ->where('status', 1);

            self::applyProjectVisibility($query, 'm');
            // 处理漫画和翻译
            self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

            if ($date) {
                // 兼容格式：2025-01-01 或 20250101
                if (preg_match('/^\d{8}$/', $date)) {
                    $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
                }

                $timestamp = strtotime($date . ' 00:00:00');
                if ($timestamp !== false) {
                    $query->where('update_time', '>=', $timestamp);
                }
            }

            switch ($type) {
                case 1:
                    $order = 'monthly_mark desc';
                    break;
                case 2:
                    $order = 'monthly_view desc';
                    break;
                case 3:
                    $where[] = ['mhstatus', '=', 1];
                    break;
                case 4:
                    $order = 'monthly_subscribe desc';
                    break;
            }
            $results = $query->order($order)->paginate([
                'list_rows' => $limit,
                'page' => $page,
            ])->toArray();

            $results['data'] = injectTicaiName($results['data'], $lang);

            if (!empty($results['data'])) {
                self::appendMaxChapterSort($results['data']);

                // 封面图处理
                foreach ($results['data'] as &$comic) {
                    if (empty($comic['cover_horizontal']) && !empty($comic['image'])) {
                        $comic['cover_horizontal'] = $comic['image'];
                    }
                }
                unset($comic); // 避免引用问题

                $redis->set($redis_key, $results, 86400);
            }
        }
        return $results;
    }

    public static function ticai($lang)
    {
        $redis = new Redis();
        $redis_key = 'comic_ticai_' . $lang;
        $results = $redis->get($redis_key);
        if (!$results) {
            $query = Ticai::alias('tc')
                ->field('tc.id,tc.name,tc.is_top')
                ->where('switch', '=', 1);

            // 处理漫画和翻译
            self::withTranslation($query, 'tc', $lang, ['name'], 'qiswl_ticai_trans', 'ticai_id');

            $results = $query->select();

            if ($results)
                $redis->set($redis_key, $results, 86400); //1天
        }
        return $results;
    }

    // ------------------------
    // 标签
    // ------------------------
    public static function tags($lang)
    {
        $redis = new \Redis();
        $redis_key = 'comic_tags_' . $lang;
        $results = $redis->get($redis_key);

        if (!$results) {
            $query = self::alias('t')
                ->field('t.id, t.name, t.is_top')
                ->where('status', 1)
                ->order('sort ASC');

            // 多语言翻译
            self::withTranslation($query, 't', $lang, ['name'], 'tag_trans', 'tag_id');

            $results = $query->select();

            if ($results) {
                $redis->set($redis_key, $results, 86400); // 缓存 1 天
            }
        }

        return $results;
    }

    public static function lists($page, $limit, $ticai_id, $tag, $keyword, $mhstatus, $xianmian, $year, $month, $weekday, $type, $lang)
    {
        $redis = new Redis();
        $redis_key = 'comic_lists_' . implode('_', [$page, $limit, $ticai_id, $tag, $keyword, $mhstatus, $xianmian, $year, $month, $weekday, $type, $lang]);
        $results = $redis->get($redis_key);
        $order = 'update_time desc';

        if (!$results) {
            $query = self::alias('m')
                ->field(self::list_tran_field)
                ->where('status', 1);

            self::applyProjectVisibility($query, 'm');
            // 处理漫画和翻译
            self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

            if ($ticai_id) {
                $query->where('ticai_id', $ticai_id);
            }

            // 搜索基于语言
            if ($keyword) {
                $query->where(function ($q) use ($lang, $keyword) {
                    if ($lang === 'zh') {
                        $q->whereRaw("instr(m.title, ?) > 0 OR instr(m.auther, ?) > 0", [$keyword, $keyword]);
                    } else {
                        $q->whereRaw("instr(t.title, ?) > 0", [$keyword]);
                    }
                });
            }

            if ($tag) {
                $query->whereRaw('FIND_IN_SET(?, tags)', [$tag]);
            }

            if (in_array($mhstatus, [0, 1, '0', '1'], true)) {
                $query->where('mhstatus', $mhstatus);
            }

            if (in_array($xianmian, [0, 1, '0', '1'], true)) {
                $query->where('xianmian', $xianmian);
            }

            if ($weekday) {
                $mysql_weekday = ((int) $weekday % 7) + 1;
                $query->whereRaw('DAYOFWEEK(FROM_UNIXTIME(update_time)) = ?', [$mysql_weekday]);
            }

            if ($month) {
                $year = $year ?: date('Y');
                $start = strtotime("$year-$month-01 00:00:00");
                $end = strtotime("+1 month", $start);
                $query->whereBetween('update_time', [$start, $end]);
            } elseif ($year) {
                $start = strtotime("$year-01-01 00:00:00");
                $end = strtotime("+1 year", $start);
                $query->whereBetween('update_time', [$start, $end]);
            }

            switch ($type) {
                case 1:
                    $order = 'monthly_view desc';
                    break;
                case 3:
                    $order = 'monthly_subscribe desc';
                    break;
                case 4:
                    $order = 'monthly_mark desc';
                    break;
            }

            $results = $query->order($order)->paginate([
                'list_rows' => $limit,
                'page' => $page,
            ])->toArray();

            if (!empty($results['data'])) {
                self::appendMaxChapterSort($results['data']);
                $redis->set($redis_key, $results, 86400);
            }
        }

        // shuffle($results['data']);
        return $results;
    }

    public static function info($id, $page, $limit, $lang, $sort = 'asc')
    {
        $redis = new Redis();
        $redis_key = 'comic_info_' . $id . '_' . $lang;
        $results = $redis->get($redis_key);

        if (!$results) {
            // 处理漫画和翻译
            $query = self::with(['ManhuaHighlights'])
                ->alias('m')
                ->field(self::list_tran_field)
                ->where('m.id', '=', $id);

            self::applyProjectVisibility($query, 'm');
            self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

            $comic = $query->find();

            if (empty($comic['cover_horizontal']) && !empty($comic['image'])) {
                $comic['cover_horizontal'] = $comic['image'];
            }

            // 处理漫画演员和翻译
            $actorQuery = ManhuaActor::alias('a')
                ->field('a.id, a.manhua_id, a.img')
                ->where('a.manhua_id', $id);

            self::withTranslation($actorQuery, 'a', $lang, ['name'], 'qiswl_manhua_actor_trans', 'manhua_actor_id');

            $actorList = $actorQuery->select();

            if ($comic) {
                $comic['manhua_actors'] = $actorList;
                $redis->set($redis_key, $results, 3600); // 只缓存漫画本体
            }

            $results = $comic;
        }

        if (!$results)
            return null;

        // 校验 sort 参数，避免 SQL 注入风险（仅允许 asc / desc）
        $sort = strtolower($sort);
        if (!in_array($sort, ['asc', 'desc'])) {
            $sort = 'asc';
        }

        // 处理漫画章节和翻译
        $chapterQuery = Chapter::alias('c')
            ->field('c.id,c.image,c.isvip,c.update_time,c.score,c.manhua_id,c.sort')
            ->where('c.manhua_id', $id)
            ->where('c.switch', 1)
            ->order('c.sort', $sort);

        self::withTranslation($chapterQuery, 'c', $lang, ['title'], 'qiswl_capter_trans', 'capter_id');
        $chapters = $chapterQuery->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ])->toArray();

        if (!empty($results['xianmian']) && $results['xianmian'] == 1) {
            foreach ($chapters['data'] as &$chapter) {
                $chapter['isvip'] = 0; // 强制限免
            }
        }

        return [
            'comic' => $results,
            'chapters' => $chapters,
        ];
    }


    public static function history($uid, $page, $limit, $lang)
    {
        $historyModel = new History();
        $historyTable = $historyModel->getFullTableName();

        $subQuery = Db::connect()
            ->table($historyTable)
            ->alias('h')
            ->where('h.member_id', $uid)
            ->field('h.manhua_id, MAX(h.addtime) as max_addtime')
            ->group('h.manhua_id')
            ->buildSql();

        $query = self::alias('m')
            ->join([$subQuery => 'h'], 'h.manhua_id = m.id', 'inner')
            ->field([
                'm.id',
                'm.title',
                'm.image',
                'm.cover',
                'm.auther',
                'm.mhstatus',
                'm.xianmian',
                'm.issole',
                'm.ticai_id',
                'm.last_chapter_title',
                'm.view',
                'm.mark',
                'm.subscribe',
                'm.desc',
                'm.age18',
                'h.max_addtime as addtime',
            ])
            ->where('m.status', 1)
            ->order('h.max_addtime', 'desc');

        self::applyProjectVisibility($query, 'm');
        self::withTranslation(
            $query,
            'm',
            $lang,
            ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'],
            'qiswl_manhua_trans',
            'manhua_id',
        );

        $results = $query->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ])->toArray();

        if (!empty($results['data'])) {
            self::appendMaxChapterSort($results['data']);
        }

        return $results;
    }

    public static function subscribe($uid, $page, $limit, $lang)
    {
        $subscribeTable = (new Subscribe())->getFullTableName();

        $query = self::alias('m')
            ->join("{$subscribeTable} b", 'b.manhua_id = m.id', 'inner') // 动态跨库 join
            ->field(self::inter_tran_field)
            ->where('b.member_id', '=', $uid)
            ->where('b.status', '=', 1)
            ->where('m.status', '=', 1)
            ->order('b.addtime desc');

        self::applyProjectVisibility($query, 'm');
        self::withTranslation(
            $query,
            'm',
            $lang,
            ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'],
            'qiswl_manhua_trans',
            'manhua_id',
        );

        $results = $query->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ])->toArray();

        if (!empty($results['data'])) {
            self::appendMaxChapterSort($results['data']);
        }

        return $results;
    }


    public static function favorites($uid, $page, $limit, $lang)
    {

        $favoritesTable = (new Favorites())->getFullTableName();

        $query = self::alias('m')
            ->join("{$favoritesTable} b", 'b.manhua_id = m.id', 'inner')
            ->field(self::inter_tran_field)
            ->where('b.member_id', '=', $uid)
            ->where('b.status', '=', 1)
            ->where('m.status', '=', 1)
            ->order('b.addtime desc');

        self::applyProjectVisibility($query, 'm');
        self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

        $results = $query->paginate([
            'list_rows' => $limit,
            'page' => $page,
        ])->toArray();

        if (!empty($results['data'])) {
            self::appendMaxChapterSort($results['data']);
        }

        return $results;
    }

    public static function guess($limit = 10, $lang, $based_comic_id = null)
    {
        $groupCount = 10;
        $totalLimit = $limit * $groupCount;

        $redis = new Redis();
        $redis_key = 'comic_guess_' . $limit . '_' . $lang . '_' . $based_comic_id;
        $results = $redis->get($redis_key);

        if (!$results) {
            $where = "status = 1";

            if ($based_comic_id) {
                $comic = self::field('id, ticai_id, tags')->find($based_comic_id);
                if ($comic) {
                    if ($comic['ticai_id']) {
                        $where .= " AND ticai_id = " . intval($comic['ticai_id']);
                    }

                    $tags = $comic['tags'];
                    if (!empty($tags)) {
                        $tagConditions = [];
                        foreach ($tags as $tag) {
                            $tagId = intval($tag->id);
                            if ($tagId) {
                                $tagConditions[] = "find_in_set($tagId, tags)";
                            }
                        }

                        if (!empty($tagConditions)) {
                            $where .= " AND (" . implode(' OR ', $tagConditions) . ")";
                        }
                    }

                    $where .= " AND id != " . intval($comic['id']);
                }
            }

            $count = self::where($where)->count();
            $offset = mt_rand(0, max(0, $count - $totalLimit));

            $query = self::alias('m')
                ->field(self::list_tran_field)
                ->where($where);

            self::applyProjectVisibility($query, 'm');
            self::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

            $data = $query->limit($offset, $totalLimit)
                ->select()
                ->toArray();

            if (!empty($data)) {
                self::appendMaxChapterSort($data);
            }

            // 补足数据
            $currentCount = count($data);
            if ($currentCount < $totalLimit && $currentCount > 0) {
                $needed = $totalLimit - $currentCount;
                // 随机重复已有数据来填满
                for ($i = 0; $i < $needed; $i++) {
                    $data[] = $data[array_rand($data)];
                }
            }

            // 如果结果为空，就直接设置为空的组
            if (empty($data)) {
                $groups = array_fill(0, $groupCount, []);
            } else {
                // 分组成 10 组
                $groups = array_chunk($data, $limit);
            }

            $results = [
                'total' => $count,
                'groups' => $groups,
            ];

            if (!empty($groups)) {
                $redis->set($redis_key, json_encode($results), 86400);
            }
        } else {
            $results = json_decode($results, true);
        }

        return $results;
    }

    public static function appendMaxChapterSort(array &$manhuaList)
    {
        if (empty($manhuaList)) {
            return;
        }

        // 获取所有 manhua_id
        $manhuaIds = array_column($manhuaList, 'id');

        // 批量查最大 sort
        $sortList = Db::name('capter')
            ->whereIn('manhua_id', $manhuaIds)
            ->field('manhua_id, MAX(sort) as max_sort')
            ->group('manhua_id')
            ->select();

        // 转换为 map，方便赋值
        $sortMap = array_column($sortList, 'max_sort', 'manhua_id');

        // 循环追加 max_sort 字段
        foreach ($manhuaList as &$item) {
            $item['max_sort'] = $sortMap[$item['id']] ?? 0;
        }
    }
}
