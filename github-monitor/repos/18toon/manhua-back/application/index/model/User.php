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
    protected $name = 'member';

    public function getRegisterTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getIsvipStatusAttr($val,$data){
        $val = 0;
        $time = time();
        if($data['viptime'] > $time){
            $val = 1;
        }
        return $val;
    }
}