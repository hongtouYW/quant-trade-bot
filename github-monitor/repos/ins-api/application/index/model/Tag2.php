<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Tag2 extends Model
{
    const list_field = 'id,name,name_en,name_ru,image';
    protected $hidden = ['name_en','name_ru'];



    public function getNameAttr($val,$data)
    {
        $lang = getLang();
        if($lang == 'en' && !empty($data['name_en'])){
            return $data['name_en'];
        }

        if($lang == 'ru' && !empty($data['name_ru'])){
            return $data['name_ru'];
        }
        return $val;
    }

    // public function getImageAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }

    public static function lists($page,$limit,$keyword){

        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'video_tags2_'.$page.'_'.$limit.'_'.$keyword.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $where = "is_show = 1";
            if($keyword){
                $keyword = trim($keyword);
                $where .= " and (instr(name, '".$keyword."') or instr(name_en, '".$keyword."') or instr(name_ru, '".$keyword."'))";
            }
            $order = 'sort desc,id asc';
            $results = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 86400);;
        }

        return $results;
    }
}