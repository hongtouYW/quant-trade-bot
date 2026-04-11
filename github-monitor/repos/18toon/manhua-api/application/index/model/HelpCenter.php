<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 13:22
 */

namespace app\index\model;


use think\cache\driver\Redis;
use think\Model;

class HelpCenter extends Model
{
    protected $name = 'helpcenter';

    public static function lists()
    {

        $redis = new Redis();
        $redis_key = 'helpcenter';
        $results = $redis->get($redis_key);
        if(!$results){
            $results = self::field('id,title,desc')->where("is_show","1")->select()->toArray();
            if($results) $redis->set($redis_key, $results, 86400); //1天
        }
        return $results;
    }


}