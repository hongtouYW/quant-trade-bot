<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 15:24
 */

namespace app\index\model;


use think\Model;

class Feedback extends Model
{
    public function getAddTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }
}