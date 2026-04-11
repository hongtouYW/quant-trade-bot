<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Notice extends Model
{
    const  list_field = 'title,title_en,title_ru,content,content_en,content_ru';
    protected $hidden = ['title_en', 'title_ru', 'content_en', 'content_ru'];
    public function getTitleAttr($val, $data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['title_en'])) {
            return $data['title_en'];
        }

        if ($lang == 'ru' && !empty($data['title_ru'])) {
            return $data['title_ru'];
        }
        return $val;

    }


    public function getContentAttr($val, $data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['content_en'])) {
            return $data['content_en'];
        }

        if ($lang == 'ru' && !empty($data['content_ru'])) {
            return $data['content_ru'];
        }
        return $val;
    }


    public static function lists($site){
        $lang = getLang();
        $redis_key = 'notice_list_'.$site.'_'.$lang;
        $redis = new Redis();
        $notice_list = $redis->get($redis_key);
        if(!$notice_list){
            $where = [];
            $where[] = ['is_show','=',1];
            $where[] = ['type','=',$site];
            $notice_list = self::field(self::list_field)->where($where)->order('sort desc,id desc')->select()->toArray();
            if($notice_list){
                $redis->set($redis_key,$notice_list,86400);
            }
        }
        return $notice_list;
    }

}