<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/14
 * Time: 1:31
 */

namespace app\index\model;


use think\Model;

class Code extends Model
{

    // 开启自动写入时间戳
    protected $autoWriteTimestamp = true;  

    public function getCreateTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getExchangeTimeAttr($value){
        if($value){
            return date('Y-m-d H:i:s',$value);
        }
        return '';
    }

    public function getUidAttr($value){
        if($value){
            return $value;
        }
        return '';
    }
}