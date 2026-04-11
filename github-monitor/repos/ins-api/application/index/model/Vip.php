<?php

namespace app\index\model;

use think\Model;

class Vip extends Model
{


    protected $hidden = ['title_en','title_ru','des_en','des_ru'];

    public function getTitleAttr($val,$data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['title_en'])){
            return $data['title_en'];
        }
        if($lang == 'ru' && !empty($data['title_ru'])){
            return $data['title_ru'];
        }
        return $val;
    }

    public function getDesAttr($val,$data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['des_en'])){
            return $data['des_en'];
        }
        if($lang == 'ru' && !empty($data['des_ru'])){
            return $data['des_ru'];
        }
        return $val;
    }


    public static function lists(){
        $lists = self::field('id,title,title_en,title_ru,des,des_en,des_ru,day,cost,money,is_sale')->where('status','=',1)->order('sort desc')->select();
        return $lists;
    }
}