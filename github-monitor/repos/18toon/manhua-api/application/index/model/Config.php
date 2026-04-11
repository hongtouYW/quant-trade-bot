<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/4/29
 * Time: 2:55
 */

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use app\lib\exception\BaseException;

class Config extends Model
{

    const noReturn = ['CDN_SITE', 'API_URL'];

    public static function lists()
    {
        //Config save to redis
        $redis_key = 'config';
        $redis = new Redis();
        $arr = $redis->get($redis_key);
        if (!$arr) {
            $arr = [];
            $data = self::field('name,value')->select()->toArray();
            foreach ($data as $k => $v) {
                if (!in_array($v['name'], self::noReturn)) {
                    $key = strtolower($v['name']);
                    $arr[$key] = $v['value'];
                }
            }
            //save cache
            if ($arr) $redis->set($redis_key, $arr, 86400);
        }
        return $arr;
    }


    public static function get($name)
    {

        //Config save to redis
        $redis = new Redis();
        $redis_key = 'config_' . $name;
        $results = $redis->get($redis_key);

        if (!$results) {
            $results = self::where("name", '=', $name)->value('value');
            if (!isset($results)) throw new BaseException(1003);
            //save cache
            $redis->set($redis_key, $results, 86400);
        }
        return $results;
    }
}
