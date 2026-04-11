<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/5/25
 * Time: 13:42
 */

namespace app\index\model;


use think\Model;

class Statistic extends Model
{

    public function getSiteAttr($val){

        $arr = [
            '1'=>'有码站',
            '2'=>'无码站',
            '3'=>'动漫站',
            '4'=>'4k站',
        ];

        return $arr[$val];
    }


    public function getPidAttr($val){
        $arr = [
            '1'=>'唐朝',
            '2'=>'唐朝',
            '3'=>'永付',
            '4'=>'楹联',
            '5'=>'Tai',
            '6'=>'果汁',
            '7'=>'外星人'
        ];
        return $arr[$val];
/*        return Platforms::where('id','=',$val)->value('remark');*/
    }
}