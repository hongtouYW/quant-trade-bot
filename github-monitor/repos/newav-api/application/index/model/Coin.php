<?php

namespace app\index\model;

use think\Model;
use think\cache\driver\Redis;
use app\lib\exception\BaseException;

class Coin extends Model
{
    const info_field  = 'id,title,title_en,title_ru,title_ms,title_th,title_es,des,des_en,des_ru,des_ms,des_th,des_es,diamonds,points,cost,money,is_sale,hot,recommend';
    const list_field  = 'id,title,title_en,title_ru,title_ms,title_th,title_es,des,des_en,des_ru,des_ms,des_th,des_es,diamonds,points,cost,money,is_sale,hot,recommend';
    protected $hidden = ['title_en','title_ru','title_ms','title_th','title_es','des_en','des_ru','des_ms','des_th','des_es'];

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

    public function getDesAttr($val,$data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'des',
            'en' => 'des_en',
            'ru' => 'des_ru',
            'ms' => 'des_ms',
            'th' => 'des_th',
            'es' => 'des_es',
        ];
        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public static function info($id)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'coin_info_' .$id.'_'.$lang;
        $results   = $redis->get($redis_key);

        if (!$results) {
            $results = self::field(self::info_field)
                ->where('id','=', $id)->where('status','=',1)->find();
            if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        if (!$results) throw new BaseException(4001);
        return $results;
    }

    public static function lists(){
        $lists = self::field(self::list_field)->where('status','=',1)->order('sort asc')->select();
        return $lists;
    }
}