<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use app\lib\exception\BaseException;
use think\Db;

class Publisher extends Model
{
    const list_field = 'id,name,name_en,name_ru,name_ms,name_th,name_es,image,background_image';
    const info_field = 'id,name,name_en,name_ru,name_ms,name_th,name_es,image,background_image';

    protected $hidden = ['name_zh','name_en','name_ru','name_ms','name_th','name_es'];

    public function getNameAttr($val,$data)
    {
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

    public function getIsSubscribeAttr($val, $data)
    {
        $is_subscribe = 0;
        $uid          = getUid();

        if ($uid) {
            $count = Db::name('publisher_subscribe')
                ->where('user_id', '=', $uid)
                ->where('publisher_id', '=', $data['id'])
                ->count();

            if ($count) {
                $is_subscribe = 1;
            }
        }

        return $is_subscribe;
    }

    public static function lists($page,$limit,$keyword)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'publisher_list_'.$page.'_'.$limit.'_'.$keyword.'_'.$lang;
        $results   = $redis->get($redis_key);

        if(!$results){
            $where = '';
            if($keyword){
                $keyword = trim($keyword);
                $where   = "(instr(name, '" . $keyword . "') or instr(name_zh, '" . $keyword . "') or instr(name_twzh, '" . $keyword . "') or instr(name_en, '" . $keyword . "') or instr(name_ru, '" . $keyword . "'))";
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 86400);;
        }
        
        foreach ($results['data'] as &$publisher) {
            $publisher = self::find($publisher['id']);
            $publisher->append(['is_subscribe']);
        }
        return $results;
    }

    public static function info($pid)
    {
        $random_record = Configs::get('random_record');
        $redis         = new Redis();
        $lang          = getLang();
        $redis_key     = 'publisher_info_' . $pid.'_'.$lang;
        $results       = $redis->get($redis_key);
        if (!$results) {
            $results = self::field(self::info_field)->where('id','=', $pid)->find();
            if(!$results) throw new BaseException(3003);
            $redis->set($redis_key, $results, 3600);;
            // if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        
        if (!empty($random_record) && strpos($random_record, ',') !== false) {
            list($min, $max) = array_map('intval', explode(',', $random_record));
            $results['subscribe_count'] = mt_rand($min, $max);
        } else {
            $results['subscribe_count'] = Db::name('publisher_subscribe')->where('publisher_id', $pid)->count();
        }
        $results['total_video']     = Video::where('publisher', $pid)->count();
        return $results->append(['is_subscribe']);
    }

    public static function subscribe($uid, $publisher_id)
    {
        $exists = Db::name('publisher_subscribe')
            ->where('user_id', $uid)
            ->where('publisher_id', $publisher_id)
            ->find();

        if ($exists) {
            // unsubscribe
            return Db::name('publisher_subscribe')
                ->where('user_id', $uid)
                ->where('publisher_id', $publisher_id)
                ->delete() !== false ? false : null;
        } else {
            // subscribe
            return Db::name('publisher_subscribe')->insert([
                'user_id'      => $uid,
                'publisher_id' => $publisher_id,
                'add_time'     => time()
            ]) ? true : null;
        }
    }

    public static function mySubscribe($uid, $page = 1, $limit = 20)
    {
        $query = self::alias('p')
            ->join('publisher_subscribe ps', 'ps.publisher_id = p.id')
            ->field('p.*, ps.add_time as subscribe_time')
            ->where('ps.user_id', $uid)
            ->order('ps.add_time DESC');
        
        // Get the paginator instance
        $paginator = $query->paginate([
            'list_rows' => $limit,
            'page' => $page
        ]);
        
        // Transform each item to apply accessors
        $paginator->each(function ($item) {
            $publisher = self::find($item['id']);
            return $publisher->append(['is_subscribe']);
        });
        
        return $paginator;
    }
}