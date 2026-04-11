<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/17
 * Time: 15:58
 */

namespace app\index\model;


use think\Model;

class Order extends Model
{

    public function getUserNameAttr($val,$data){
        return User::where('id','=',$data['member_id'])->value('username');
    }

    public function getIntroAttr($val,$data){
        return Pro::where('id','=',$data['pro_id'])->value('intro');
    }
    public function getQudaodesAttr($val,$data){
        return Platforms::where('id','=',$data['paydata'])->value('qudaodes');
    }

    public function getMoneyAttr($val){
        return $val;
    }

}