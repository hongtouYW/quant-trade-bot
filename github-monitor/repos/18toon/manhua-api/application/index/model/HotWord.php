<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class HotWord extends Model
{
    public static function lists(){

        $redis = new Redis();
        $redis_key = 'hot_work';
        $results = $redis->get($redis_key);
        if(!$results){
            $results = self::where("status",'=',1)->order('sort desc')->column('keyword');
            if($results) $redis->set($redis_key, $results, 7200); //1天
        }
        return $results;
    }
}