<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 16:15
 */

namespace app\index\model;


use think\Model;

class Order extends Model
{
    protected $table="vip_order";


    public function getSiteAttr(){

        return 1;
    }

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