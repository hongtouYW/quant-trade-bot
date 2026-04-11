<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/17
 * Time: 11:32
 */

namespace app\index\model;

class User extends BaseModel
{
    public function getRegTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getIsVipAttr($value,$data){

        $value = 0;
        if($data['vip_end_time'] > time()){
            $value = 1;
        }
        return $value;
    }
}