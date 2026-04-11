<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Links extends Model
{
    protected $table= 'links';
    public static function lists(){

        $redis = new Redis();
        $redis_key = 'links';
        $lists = $redis->get($redis_key);

        if(!$lists)
        {
            $where = [];
            $where[] = ['status','=',1];
            $lists = self::field('id,title,image,url')->where($where)->order('sort desc,id desc')->select()->toArray();
            $redis->set($redis_key,$lists,3600);
        }

        return $lists;
    }
}