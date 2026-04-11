<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\cache\driver\Redis;
use think\Model;

class Banner extends Model
{
    use HasTranslation;
    

    public static function lists($positions, $lang)
    {
        $redis = new Redis();
        $redis_key = 'banner_' . implode('_', $positions) . '_' . $lang;
        $result = $redis->get($redis_key);
        if (!$result) {
            $query = self::alias('b')
                ->field('b.id,b.title,b.image,b.mid,b.link,b.position')
                ->whereIn('b.position', $positions)
                ->where('b.status', '=', 1)
                ->order('b.sort desc');

            self::withTranslation($query, 'b', $lang, ['image'], 'qiswl_banner_trans', 'banner_id');

            $result = $query->select();

            if ($result) {
                $redis->set($redis_key, $result, 86400);
            }
        }
        return $result;
    }
}
