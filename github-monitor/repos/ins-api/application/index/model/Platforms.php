<?php

namespace app\index\model;

use think\Model;

class Platforms extends Model
{

    protected $hidden = ['name_en','name_ru'];

    public function getNameAttr($val,$data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['name_en'])){
            return $data['name_en'];
        }
        if($lang == 'ru' && !empty($data['name_ru'])){
            return $data['name_ru'];
        }
        return $val;
    }
}