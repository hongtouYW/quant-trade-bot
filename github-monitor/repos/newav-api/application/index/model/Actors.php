<?php

namespace app\index\model;

use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Actors extends Model
{
    protected $table = 'actor';
    const list_field = 'id,name,name_en,name_ru,name_ms,name_th,name_es,image,background_image,video_count';
    const info_field = 'id,name,name_en,name_ru,name_ms,name_th,name_es,image,background_image,tid,video_count,video_count1,birthday,debut,bust,waist,hip,bra_size,height,nationality,constellation,blood_type,twitter,instagram,tiktok,fantia,patreon,onlyfans,youtube,x_platform,facebook';

    protected $hidden = ['video_count1','name_en','name_ru','name_ms','name_th','name_es'];

    public function getNameAttr($val,$data){
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'name',
            'en' => 'name_en',
            'ru' => 'name_ru',
            'ms' => 'name_ms',
            'th' => 'name_th',
            'es' => 'name_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getImageAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getBackgroundImageAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getIsSubscribeAttr($val,$data){
        $is_subscribe = 0;
        $uid          = getUid();
        $count        = ActorSubscribe::where('uid','=',$uid)->where('actor_id','=',$data['id'])->count();
        if($count){
            $is_subscribe = 1;
        }
        return $is_subscribe;
    }

    public function getNationalityAttr($val)
    {
        if ($val) {
            $lang = strtolower(getLang());
            $langMap = [
                'zh' => 'name',
                'en' => 'name_en',
                'ru' => 'name_ru',
                'ms' => 'name_ms',
                'th' => 'name_th',
                'es' => 'name_es',
            ];

            $field = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
            $data  = Db::name('nationality')->field("id, {$field} as name")->where('id', $val)->find();
            return $data ? $data['name'] : null;
        }

        return null;
    }
    
    public function getConstellationAttr($val) {
    if ($val) {
            $lang = strtolower(getLang());
            $langMap = [
                'zh' => 'name',
                'en' => 'name_en',
                'ru' => 'name_ru',
                'ms' => 'name_ms',
                'th' => 'name_th',
                'es' => 'name_es',
            ];

            $field = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
            $data  = Db::name('constellation')->field("id, {$field} as name")->where('id', $val)->find();
            return $data ? $data['name'] : null;
        }
        return null;
    }
    
    public function getBloodTypeAttr($val) {
    if ($val) {
            $lang = strtolower(getLang());
            $langMap = [
                'zh' => 'name',
                'en' => 'name_en',
                'ru' => 'name_ru',
                'ms' => 'name_ms',
                'th' => 'name_th',
                'es' => 'name_es',
            ];

            $field = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
            $data  = Db::name('blood_type')->field("id, {$field} as name")->where('id', $val)->find();
            return $data ? $data['name'] : null;
        }
        return null;
    }

    public static function lists($page, $limit, $order, $keyword) {
        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'actor_list_'.$page.'_'.$limit.'_'.$order.'_'.$keyword.'_'.$lang;
        $results = $redis->get($redis_key);

        if(!$results) {
            $where = "is_show = 1";
            if($keyword) {
                $keyword = trim($keyword);
                $where .= " and (instr(name, '".$keyword."') or instr(name_zh, '".$keyword."') or instr(name_twzh, '".$keyword."') or instr(name_en, '".$keyword."') or instr(name_ru, '".$keyword."'))";
            }
            
            // Updated order switch with new case 5
            switch ($order) {
                case 1: $order = 'play desc'; break;
                case 2: $order = 'play_month desc'; break;
                case 3: $order = 'play_week desc'; break;
                // case 4: $order = 'sort asc,play_week desc'; break;
                case 4: $order = 'id asc'; break; // Temporary, will handle subscription sort separately
                case 5: $order = 'sort asc'; break;
            }
            
            $query = self::field(self::list_field)
                ->where($where);
                
            // Handle subscription count sorting differently
            if ($order === 'id asc') { // This is our case 5
                $query = $query->order('id asc'); // Initial order to ensure consistent pagination
            } else {
                $query = $query->order($order);
            }
            
            $results = $query->paginate([
                'list_rows' => $limit,
                'page' => $page,
            ])->toArray();

            if ($results['data']) {
                // Add subscription counts to each actor
                foreach ($results['data'] as &$actor) {
                    $actor['subscribe_count'] = ActorSubscribe::where('actor_id', $actor['id'])->count();
                }
                
                // Apply subscription count sorting if needed
                if ($order === 'id asc') {
                    usort($results['data'], function($a, $b) {
                        return $b['subscribe_count'] - $a['subscribe_count'];
                    });
                }
                
                $redis->set($redis_key, $results, 86400);
            }
        } else {
            // For cached results, ensure subscription count exists
            foreach ($results['data'] as &$actor) {
                if (!isset($actor['subscribe_count'])) {
                    $actor['subscribe_count'] = ActorSubscribe::where('actor_id', $actor['id'])->count();
                }
            }
        }

        foreach ($results['data'] as &$item) {
            $model = self::find($item['id']);
            $item['is_subscribe'] = $model ? $model->getAttr('is_subscribe') : 0;
        }
        
        return $results;
    }

    public static function getAid(){
        $aid = getInput('aid');
        $id = self::where('id','=',$aid)->value('id');
        if(!$id){
            throw new BaseException(3003);
        }
        return $id;
    }

    public static function info($aid){
        $random_record = Configs::get('random_record');
        $redis         = new Redis();
        $lang          = getLang();
        $redis_key     = 'actor_info_'.$aid.'_'.$lang;
        $results       = $redis->get($redis_key);

        if(!$results){
            $results = self::field(self::info_field)
                ->where('id','=',$aid)
                ->find();

            if(!$results) {
                throw new BaseException(3003);
            }

            // Load 写真 manually
            $photos = Db::name('actor_photos')
                ->where('actor_id', $aid)
                ->order('sort')
                ->field('id,image,sort')
                ->select();

            $results['photos'] = $photos;
            $redis->set($redis_key, $results, 86400);
        }

        if (!empty($random_record) && strpos($random_record, ',') !== false) {
            list($min, $max) = array_map('intval', explode(',', $random_record));
            $results['subscribe_count'] = mt_rand($min, $max);
        } else {
            $results['subscribe_count'] = ActorSubscribe::where('actor_id', $aid)->count();
        }
        return $results->append(['is_subscribe']);
    }


    public static function up_play($actor){
        $time = time();
        $play_time=self::where('id','=',$actor)->value('play_time');
        if(date('d',$play_time)==date('d',$time)){
            $data['play_day']=Db::raw('play_day+1');
        }else{
            $data['play_day']=1;
        }
        if(date('W',$play_time)==date('W',$time)){
            $data['play_week']=Db::raw('play_week+1');
        }else{
            $data['play_week']=1;
        }
        if(date('m',$play_time)==date('m',$time)){
            $data['play_month']=Db::raw('play_month+1');
        }else{
            $data['play_month']=1;
        }
        $data['play']=Db::raw('play+1');
        $data['play_time']=time();
        self::where('id','=',$actor)->update($data);
    }

    /**
     * Get actors by publisher ID
     */
    public static function byPublisher($publisher_id, $page = 1, $limit = 20)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = "actors_by_publisher_{$publisher_id}_{$page}_{$limit}_{$lang}";
        $results   = $redis->get($redis_key);

        if (!$results) {
            // Get actor IDs from videos that belong to this publisher
            $actorIds = Db::name('video')
                ->where('publisher', $publisher_id)
                ->where('actor', '<>', '')
                ->group('actor')
                ->column('actor');
            if (empty($actorIds)) {
                return [
                    'data'         => [],
                    'total'        => 0,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => 1
                ];
            }

            $results = self::whereIn('id', $actorIds)
                ->field(self::list_field)
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page
                ])->toArray();
            if ($results['data']) {
                foreach ($results['data'] as &$actor) {
                    $actorModel               = self::find($actor['id']);
                    $actor['is_subscribe']    = $actorModel->getAttr('is_subscribe');
                    $actor['subscribe_count'] = ActorSubscribe::where('actor_id', $actor['id'])->count();
                }
                $redis->set($redis_key, $results, 1); // Cache for 1 hour
            }
        }
        return $results;
    }

    public static function getActorRanking($filter = 'day', $page = 1, $limit = 30)
    {
        $redis          = new Redis();
        $lang           = getLang();
        $delayHours     = Configs::get('video_delay_hours');
        $redis_key      = 'actor_ranking_'.$page.'_'.$limit.'_'.$filter.'_'.$lang;
        $results        = $redis->get($redis_key);
        $delayTimestamp = time() - ($delayHours * 3600); // only get the video insert time more than $delayHours

        if (!$results) {
            switch ($filter) {
                case 'day':
                    $orderField = 'play_day';
                    break;
                case 'week':
                    $orderField = 'play_week';
                    break;
                case 'month':
                    $orderField = 'play_month';
                    break;
                default:
                    $orderField = 'play';
            }

            // get top actors by ranking field
            $actorModels = self::where($orderField, '>', 0)
                ->order($orderField, 'desc')
                ->page($page, $limit)
                ->field("id, name, name_en, name_ru, name_ms, name_th, name_es, image, {$orderField} as view_count")
                ->select();

            if ($actorModels->isEmpty()) {
                return [];
            }

            // enrich actor data
            $actors = [];
            foreach ($actorModels as $actorModel) {
                // ensure getNameAttr & getImageAttr run
                $actorModel->append(['name', 'image']);
                $actor = $actorModel->toArray();

                // top videos for this actor
                $videos = Video::whereRaw("FIND_IN_SET(:aid, actor)", ['aid' => $actor['id']])
                    ->where('status', 1)
                    ->where('insert_time', '<=', $delayTimestamp)
                    ->order('play', 'desc')
                    ->limit(5)
                    ->field(Video::list_field)
                    ->select();

                $actor['videos'] = $videos ? $videos->toArray() : [];
                $actors[] = $actor;
            }

            // cache
            $redis->set($redis_key, json_encode($actors, JSON_UNESCAPED_UNICODE), 7200);
            $results = $actors;
        } else {
            $results = json_decode($results, true);
        }
        return $results;
    }

    private static function getTimeRange($filter) {
        $now = time();
        switch ($filter) {
            case 'day':
                $start = strtotime('today');
                $end   = $now;
                break;
            case 'week':
                $start = strtotime('last Sunday');
                $end   = $now;
                break;
            case 'month':
                $start = strtotime('first day of this month');
                $end   = $now;
                break;
            default:
                throw new BaseException(4000, 'Invalid filter');
        }
        return ['start' => $start, 'end' => $end];
    }
}