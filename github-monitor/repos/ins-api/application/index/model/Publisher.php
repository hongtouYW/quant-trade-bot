<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Publisher extends Model
{
    const list_field = 'id,name,name_en,name_ru';
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

    public static function lists($page,$limit,$site,$keyword)
    {
        switch ($site){
            case 2:
                $where = 'video_count1 > 0';
                break;
            case 4:
                $where = 'video_count2 > 0';
                break;
            case 1:
            default:
                $where = 'video_count > 0';
                break;
        }
        if($keyword){
            $keyword = trim($keyword);
            $where .= " and (instr(name, '".$keyword."') or instr(name_en, '".$keyword."') or instr(name_ru, '".$keyword."'))";
        }
        $lang = getLang();
        $redis_key = 'video_publisher_list_'.$page.'_'.$limit.'_'.$site.'_'.$keyword.'_'.$lang;
        $redis = new Redis();
        $results = $redis->get($redis_key);
        if(!$results){
            $results = self::field(self::list_field)
                ->where($where)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 86400);;
        }
        return $results;

    }

}