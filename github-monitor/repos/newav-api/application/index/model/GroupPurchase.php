<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;

class GroupPurchase extends Model
{
    protected $table = 'group_purchases';

    public static function hasPurchased($uid, $groupId)
    {
        return self::where('uid', $uid)
                   ->where('group_id', $groupId)
                   ->value('id') !== null;
    }

    public static function validateGroupPurchase($uid, $groupIds)
    {
        return self::where('uid', $uid)
            ->whereIn('group_id', $groupIds)
            ->value('id') !== null;
    }

}