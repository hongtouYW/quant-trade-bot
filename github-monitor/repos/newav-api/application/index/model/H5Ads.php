<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class H5Ads extends Model
{

    public function getThumbAttr($value)
    {
        $thumb_url = Configs::get('thumb_url');
        if (empty($value)) {
            return '';
        }
        if (strpos($value, 'http') === false) {
            return $thumb_url . $value;
        }
        $parsedUrl = parse_url($value);
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $old_url = $scheme . '://' . $parsedUrl['host'];
        return str_replace($old_url, $thumb_url, $value);
    }


    public static function lists()
    {
        $redis_key = 'ads_list_h5';
        $redis = new Redis();
        $ads = $redis->get($redis_key);
        if (!$ads) {
            $where = [];
            $where[] = ['status', '=', 1];
            for ($i = 1;$i<=7;$i++){
                $find = self::field('title,thumb,url')->where($where)->where('position','=',$i)->order('sort desc')->select();
                if ($find) {
                    $ads[$i] = $find;
                }
            }

            $redis->set($redis_key,$ads,86400);

        }
        return $ads;
    }
}