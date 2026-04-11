<?php

namespace app\index\model;

use think\Model;

class RechargeOrder extends Model
{
    public function getUserNameAttr($val,$data){
        return User::where('id','=',$data['uid'])->value('username');
    }

    public function getRemarkAttr($val,$data){
        return Platforms::where('id','=',$data['pid'])->value('remark');
    }

    public function getAddTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getPayTimeAttr($value){

        if($value > 0){
            return date('Y-m-d H:i:s',$value);
        }
        return '';
    }
}