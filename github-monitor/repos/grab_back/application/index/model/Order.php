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