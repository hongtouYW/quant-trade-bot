<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class PcAds extends Model
{

    public function getThumbAttr($value)
    {
        // $thumb_url = Configs::get('thumb_url');
        // if (empty($value)) {
        //     return '';
        // }
        // if (strpos($value, 'http') === false) {
        //     return $thumb_url . $value;
        // }
        // $parsedUrl = parse_url($value);
        // $scheme = $parsedUrl['scheme'] ?? 'https';
        // $old_url = $scheme . '://' . $parsedUrl['host'];
        // return str_replace($old_url, $thumb_url, $value);
        if (empty($value)) {
            return '';
        }
        if (strpos($value, '/') === 0) {
            return $value;
        }
        if (strpos($value, 'http') !== false) {
            $parsedUrl = parse_url($value);
            return $parsedUrl['path'] ?? '';
        }
        return $value;
    }



    public static function lists($site)
    {
        $redis_key = 'ads_list_pc_'.$site;
        $redis = new Redis();
        $ads = $redis->get($redis_key);
        if (!$ads) {
            $where = [];
            $where[] = ['status', '=', 1];
            $where[] = ['type', '=', $site];
            for ($i = 1;$i<=6;$i++){
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