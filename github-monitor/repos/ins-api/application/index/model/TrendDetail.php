<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class TrendDetail extends Model
{


    public function getCreateAtAttr($val){
        return wordTime($val);
    }

    public function getMediaAttr($val){
        $thumb_url = Configs::get('thumb_url');
        $result = [];
        if(!empty($val)){
            $val = explode(",", $val);
            foreach ($val as $k=>$v){
                $result[] = $thumb_url.$v;
            }
        }
        return $result;
    }



    public static function lists($page,$limit,$tid)
    {
        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'trend_'.$page.'_'.$limit.'_'.$tid.'_'.$lang;

        $lists = $redis->get($redis_key);
        if(!$lists){
            $where = [];
            $where[] = ['tid','=',$tid];
            $order = 'create_at desc';
            $results = self::field('text,text_en,text_ru,media,create_at')
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 7200);

        }
        return $lists;

    }


}