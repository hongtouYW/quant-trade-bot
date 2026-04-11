<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Links extends Model
{
    protected $table = 'links';

    const list_field  = 'id,title,title_en,title_ru,image,url';
    protected $hidden = ['title_en','title_ru'];

    public function getImageAttr($value)
    {
        $thumb_url = Configs::get('thumb_url');
        if (empty($value)) {
            return '';
        }
        if (strpos($value, 'http') === false) {
            return $thumb_url . $value;
        }
        $parsedUrl = parse_url($value);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $old_url = $scheme . '://' . $parsedUrl['host'];
        return str_replace($old_url, $thumb_url, $value);
    }

    public function getTitleAttr($val,$data){
        $lang = getLang();
        if ($lang == 'en' && !empty($data['title_en'])){
            return $data['title_en'];
        }
        if($lang == 'ru' && !empty($data['title_ru'])){
            return $data['title_ru'];
        }
        return $val;
    }

    public static function lists(){
        $lang = getLang();
        $redis_key = 'links_list_'.$lang;
        $redis = new Redis();
        $links_list = $redis->get($redis_key);
        if(!$links_list){
            $links_list = self::field(self::list_field)->where('status','=',1)->order('sort desc')->select()->toArray();
            if($links_list){
                $redis->set($redis_key,$links_list,86400);
            }
        }
        return $links_list;
    }
}