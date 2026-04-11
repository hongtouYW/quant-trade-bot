<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\cache\driver\Redis;
use think\Model;

class Notice extends Model
{
    use HasTranslation;

    protected $table = 'notice';

    public static function lists($lang = 'zh')
    {
        $redis = new Redis();
        $redis_key = 'notice_' . $lang; 

        $result = $redis->get($redis_key);
        if (!$result) {
            $now = time();

            $query = self::alias('n')
                ->field('n.id,n.title,n.image,n.mobile_image,n.url,n.sort,n.request_login')
                ->where('n.is_show', 1)
                ->where(function ($q) use ($now) {
                    $q->where(function ($q2) use ($now) {
                        // start_time = 0 表示无限制，或者开始时间 <= 当前
                        $q2->where('n.start_time', 0)->whereOr('n.start_time', '<=', $now);
                    })->where(function ($q2) use ($now) {
                        // end_time = 0 表示无限制，或者结束时间 >= 当前
                        $q2->where('n.end_time', 0)->whereOr('n.end_time', '>=', $now);
                    });
                })
                ->order('n.sort asc,n.id desc');

            self::withTranslation(
                $query,
                'n',
                $lang,
                ['title', 'content', 'image', 'mobile_image'],
                'notice_trans',
                'notice_id'
            );

            $result = $query->select();

            if ($result) {
                $data = $result instanceof \think\Collection ? $result->toArray() : $result;
                $redis->set($redis_key, $data, 86400); 
                return $data;
            }
        }
        return $result;
    }
}
