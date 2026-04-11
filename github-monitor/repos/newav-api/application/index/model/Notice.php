<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Notice extends Model
{
    const  list_field = 'title,title_en,title_ru,title_ms,title_th,title_es,content,content_en,content_ru,content_ms,content_th,content_es';
    protected $hidden = ['title_en','title_ru','title_ms','title_th','title_es','content_en','content_ru','content_ms','content_th','content_es'];
    public function getTitleAttr($val, $data)
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


    public function getContentAttr($val, $data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'content',
            'en' => 'content_en',
            'ru' => 'content_ru',
            'ms' => 'content_ms',
            'th' => 'content_th',
            'es' => 'content_es',
        ];
        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }


    public static function lists(){
        $lang = getLang();
        $redis_key = 'notice_list_'.$lang;
        $redis = new Redis();
        $notice_list = $redis->get($redis_key);
        if(!$notice_list){
            $where = [];
            $where[] = ['is_show','=',1];
            $notice_list = self::field(self::list_field)->where($where)->order('sort desc,id desc')->select()->toArray();
            if($notice_list){
                $redis->set($redis_key,$notice_list,86400);
            }
        }
        return $notice_list;
    }

}