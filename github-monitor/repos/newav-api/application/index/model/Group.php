<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;

class Group extends Model
{
    protected $table = 'video_groups';

    const list_field   = 'g.id,g.title,g.title_zh,g.title_en,g.title_ru,g.title_ms,g.title_th,g.title_es,g.description,g.description_zh,g.description_en,g.description_ru,g.description_ms,g.description_th,g.description_es,g.amount,g.image,g.sort';
    const list_collect = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,description,description_zh,description_en,description_ru,description_ms,description_th,description_es,amount,image';
    const info_field   = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,description,description_zh,description_en,description_ru,description_ms,description_th,description_es,description,amount,image';
    protected $hidden  = ['title_zh','title_en','title_ru','title_ms','title_th','title_es','description_zh','description_en','description_ru','description_ms','description_th','description_es'];
    protected $append  = ['total_video'];
    // protected $append  = ['is_purchase', 'is_collect', 'total_video', 'videos'];

    public function getTotalVideoAttr($value, $data)
    {
        return Db::name('video_group_details')
            ->where('group_id', $data['id'])
            ->count();
    }

    public function getVideosAttr($value, $data)
    {
        $video_ids = Db::name('video_group_details')
            ->where('group_id', $data['id'])
            ->column('video_id');

        $list = [];
        foreach ($video_ids as $vid) {
            try {
                $info = Video::info($vid);
                $list[] = $info;
            } catch (\Exception $e) {
                // Optionally skip videos with error or log them
                continue;
            }
        }
        return $list;
    }
    
    public function getImageAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getTitleAttr($val,$data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'title',
            'en' => 'title_en',
            'ru' => 'title_ru',
            'ms' => 'title_ms',
            'th' => 'title_th',
            'es' => 'title_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getDescriptionAttr($val, $data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'description',
            'en' => 'description_en',
            'ru' => 'description_ru',
            'ms' => 'description_ms',
            'th' => 'description_th',
            'es' => 'description_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }
    
    public function getIsPurchaseAttr($val,$data){
        $is_purchase = 0;
        $uid         = getUid();
        $count       = GroupPurchase::hasPurchased($uid,$data['id']);

        if($count){
            $is_purchase = 1;
        }
        return $is_purchase;
    }

    public function getIsCollectAttr($val,$data){
        $is_collect = 0;
        $uid        = getUid();
        $count      = GroupCollect::where('uid','=',$uid)->where('gid','=',$data['id'])->count();
        if($count){
            $is_collect = 1;
        }
        return $is_collect;
    }

    public static function getGid(){
        $gid   = getInput('gid');
        $group = self::field(self::info_field)->where('is_show','=',1)->where('id','=',$gid)->find();
        if(!$group){
            throw new BaseException(5001);
        }
        return $group['id'];
    }

    public static function lists($page, $limit, $keyword, $publisher_id)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'group_list_' . $page . '_' . $limit . '_' . $keyword . '_' . $lang . '_' . $publisher_id;
        $results   = $redis->get($redis_key);

        if (!$results) {
            $where = "g.is_show = 1";
            if ($keyword) {
                $keyword = trim($keyword);
                $keyword = preg_replace("/([a-zA-Z]+)\s*(\d+)/", "$1-$2", $keyword);
                $where .= " and (g.title like '%{$keyword}%' or g.title_en like '%{$keyword}%' or g.title_ru like '%{$keyword}%')";
            }

            $paginator = self::alias('g')
                ->leftJoin('video_group_details d', 'g.id = d.group_id')
                ->leftJoin('video v', 'd.video_id = v.id')
                ->where($where)
                ->whereExists(function($q) {
                    $q->table('video_group_details')
                    ->whereRaw('video_group_details.group_id = g.id');
                })
                ->when($publisher_id, function ($q) use ($publisher_id) {
                    $q->where('v.publisher', '=', $publisher_id);
                })
                ->field(self::list_field . ', COUNT(DISTINCT d.video_id) AS total_video')
                ->group('g.id')
                ->order('g.sort asc, g.id desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page,
                ]);

            $results = $paginator->toArray();

            if (!empty($results['data'])) {
                $redis->set($redis_key, $results, 3600);
            }
        }
        // Add user-specific attributes AFTER retrieving from cache
        foreach ($results['data'] as &$group) {
            $groupModel = new self($group);
            $group['is_purchase'] = $groupModel->getIsPurchaseAttr(null, $group);
            $group['is_collect'] = $groupModel->getIsCollectAttr(null, $group);
        }

        return $results;
    }

    public static function info($gid)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'group_info_' . $gid.'_'.$lang;
        $results   = $redis->get($redis_key);

        if (!$results) {
            $results = self::field(self::info_field)
            ->where('id','=', $gid)->where('is_show','=',1)->find();
            if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        if (!$results) throw new BaseException(5001);
        $results = $results->toArray();
        $groupModel = new self($results);
        $results['is_purchase'] = $groupModel->getIsPurchaseAttr(null, $results);
        $results['is_collect']  = $groupModel->getIsCollectAttr(null, $results);
        $results['videos']      = $groupModel->getVideosAttr(null, $results);
        return $results;
    }

    public static function myCollect($uid, $page = 1, $limit = 12)
    {
        $groupIds = Db::name('video_groups_collect')
            ->where('uid', $uid)
            ->order('add_time', 'desc')
            ->page($page, $limit)
            ->column('gid');

        if (empty($groupIds)) {
            return [
                'data'         => [],
                'total'        => 0,
                'per_page'     => $limit,
                'current_page' => $page
            ];
        }

        // Fetch the group data using your defined fields
        $groups = self::field(self::list_collect)
            ->where('id', 'in', $groupIds)
            ->where('is_show', 1)
            ->select()
            ->toArray();

        // inject total_video
        foreach ($groups as &$group) {
            $group['total_video'] = Db::name('video_group_details')
                ->where('group_id', $group['id'])
                ->count();
        }
        // Get total count
        $total = Db::name('video_groups_collect')->where('uid', $uid)->count();

        return [
            'data'         => $groups,
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page
        ];
    }

    public static function myPurchase($uid, $page = 1, $limit = 20){
        $groupIds = Db::name('group_purchases')
            ->where('uid', $uid)
            ->order('purchased_at', 'desc')
            ->page($page, $limit)
            ->column('group_id');

        // Fetch the group data using your defined fields
        $groups = self::field(self::list_collect)
            ->where('id', 'in', $groupIds)
            ->where('is_show', 1)
            ->select()
            ->toArray();

        // inject total_video
        foreach ($groups as &$group) {
            $group['total_video'] = Db::name('video_group_details')
                ->where('group_id', $group['id'])
                ->count();
        }
        // Get total count
        $total = Db::name('video_groups_collect')->where('uid', $uid)->count();
        return [
            'data'         => $groups,
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page
        ];
    }
}