<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Version extends Model
{
    protected $table = 'version';

    public static function getLast($type){
        $redis_key = 'version_'.$type;
        $redis = new Redis();
        $result = $redis->get($redis_key);
        if(!$result){
            $result = self::field('code,code_name,is_force,des,down_url,update_time')->where('status','=',1)->order('code desc')->find()->toArray();
            if($result){
                $redis->set($redis_key, $result,86400);
            }
        }
        return $result;

    }

}