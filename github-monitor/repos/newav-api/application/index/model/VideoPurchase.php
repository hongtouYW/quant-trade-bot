<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;

class VideoPurchase extends Model
{
    protected $table = 'video_purchases';

    public static function hasPurchased($uid, $vid)
    {
        return self::where('uid', $uid)
                   ->where('video_id', $vid)
                   ->value('id') !== null;
    }
}