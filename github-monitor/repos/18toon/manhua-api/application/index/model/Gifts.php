<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\cache\driver\Redis;
use think\Model;

class Gifts extends Model
{
    use HasTranslation;
    protected $name = 'gifts';

    /**
     * 获取推广活动（用于第一次打开网站弹出）
     * @param string $lang
     * @return array|null
     */
    public static function promotion($lang = 'zh')
    {
        $redis = new Redis();
        $redis_key = 'gift_promotion_' . $lang;
        $result = $redis->get($redis_key);

        if (!$result) {
            $now = time();

            $query = self::alias('g')
                ->field('g.id,g.name,g.start_time,g.end_time')
                ->where('g.status', 1)
                ->where('g.start_time', '<=', $now)
                ->where('g.end_time', '>=', $now)
                ->order('g.id desc');

            self::withTranslation(
                $query,
                'g',
                $lang,
                ['name'], // 翻译的字段
                'qiswl_gifts_trans', // 翻译表
                'gift_id' // 外键
            );

            $result = $query->find();
            if ($result) {
                $result = $result->toArray();
                $redis->set($redis_key, $result, 86400);
            }
        }

        return $result;
    }
}
