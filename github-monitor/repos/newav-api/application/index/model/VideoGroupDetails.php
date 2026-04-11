<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;

class VideoGroupDetails extends Model
{
    protected $table = 'video_group_details';

    public static function getVideoGroup($uid, $vid)
    {
        $groupIds = self::where('video_id', $vid)->column('group_id');

        if (empty($groupIds)) {
            return false;
        }

        return GroupPurchase::validateGroupPurchase($uid, $groupIds);
    }

}