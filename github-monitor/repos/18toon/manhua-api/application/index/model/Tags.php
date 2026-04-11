<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/4/27
 * Time: 15:07
 */

namespace app\index\model;

use app\traits\HasTranslation;
use app\traits\HasProjectVisibility;
use think\cache\driver\Redis;
use think\Model;

class Tags extends Model
{
    use HasTranslation, HasProjectVisibility;

    
    protected $table = 'tags';
    protected $hidden = ['sort', 'status'];

    public static function lists($lang)
    {
        $redis_key = 'comic_tags_' . $lang;
        $redis = new Redis();
        $arr = $redis->get($redis_key);
        if (!$arr) {
            $query = self::alias('tc')
                ->field('tc.id,tc.name')
                ->where('tc.status', '=', 1)
                ->where('tc.is_top', '=', 1)
                ->order('tc.sort desc,id asc');


            // 项目可见性过滤
            self::applyProjectVisibility($query, 'tc');

            // 处理漫画和翻译
            self::withTranslation($query, 'tc', $lang, ['name'], 'tag_trans', 'tag_id');

            $arr = $query->select();

            //save cache
            if ($arr)
                $redis->set($redis_key, $arr, 86400);
        }
        return $arr;
    }
}
