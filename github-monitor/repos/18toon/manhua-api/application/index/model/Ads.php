<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Ads extends Model
{
    public static function lists()
    {

        $redis = new Redis();
        $redis_key = 'ads';
        $result = $redis->get($redis_key);
        if (!$result) {
            $result = self::field('id,title,img,url')->where('status', '=', 1)->order('sort desc')->select();
            if ($result) {
                $redis->set($redis_key, $result, 86400);
            }
        }
        return $result;
    }
}
