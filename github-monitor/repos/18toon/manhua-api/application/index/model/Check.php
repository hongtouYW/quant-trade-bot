<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/15
 * Time: 1:20
 */

namespace app\index\model;


use app\lib\exception\BaseException;
use think\Model;

class Check extends Model
{

    /**
     * Notes:更换图像
     *
     * User: joker
     * DateTime: 2022/4/21 20:16
     */
    public static function addData($uid,$value,$type){

        $data = [
            'uid'=>$uid,
            'value'=>$value,
            'type'=>$type,
            'add_time'=>time()
        ];
        $res = self::insert($data);
        if(!$res){
            throw new BaseException(999);
        }
        return true;
    }


    public static function check($uid,$type){

        $count = self::where('uid','=',$uid)->where('type','=',$type)->where('status','=','0')->count();
        if($count){
            throw new BaseException(2018);
        }
        return true;
    }
}