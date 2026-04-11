<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Banner1 extends Model
{
    public function getThumbAttr($value)
    {
    //     $thumb_url = Configs::get('thumb_url');
    //     if (empty($value)) {
    //         return '';
    //     }
    //     if (strpos($value, 'http') === false) {
    //         return $thumb_url . $value;
    //     }
    //     $parsedUrl = parse_url($value);
    //     $scheme = $parsedUrl['scheme'] ?? 'https';
    //     $old_url = $scheme . '://' . $parsedUrl['host'];
    //     return str_replace($old_url, $thumb_url, $value);
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

    public function getH5ThumbAttr($value)
    {
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


    public static function lists(){
        $redis = new Redis();
        $redis_key = 'banner1';
        $lists = $redis->get($redis_key);
        if(!$lists){
            $where = [];
            $where[] = ['is_show','=',1];
            $lists = self::field('id,title,vid,aid,thumb,h5_thumb,url')->where($where)->order('sort desc')->select()->toArray();
            if ($lists){
                $redis->set($redis_key,$lists,86400);
            }
        }
        // Detect device
        $device = getDevice();  // 1 = PC, 2 = Mobile
        foreach ($lists as &$item) {
            if ($device == 2) {
                $item['thumb'] = $item['thumb'];
            } else {
                if (!empty($item['h5_thumb'])) {
                    $item['thumb'] = $item['h5_thumb'];
                } else {
                    $item['thumb'] = $item['thumb'];
                }
            }
            unset($item['h5_thumb']); // optional: hide h5_thumb
        }
        return $lists;
    }
}