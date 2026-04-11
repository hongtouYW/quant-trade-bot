<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\Model;
use think\Db;

class Pro extends Model
{
    use HasTranslation;

    public static function lists($lang)
    {
        // 1. 查询商品
        $query = self::alias('p')
            ->field('p.id,p.type_status,p.title,p.intro,p.ori_price,p.price,p.ishotswitch')
            ->where('status', 1)
            ->order('sort,id');

        // 处理翻译
        self::withTranslation($query, 'p', $lang, ['title', 'intro'], 'qiswl_pro_trans', 'product_id');

        // 结果兼容 Collection/array
        $lists = $query->select();
        if ($lists instanceof \think\Collection) {
            $lists = $lists->toArray();
        } else {
            $lists = (array) $lists;
        }

        if (!$lists) {
            return [];
        }

        // 2. 获取商品ID
        $proIds = array_column($lists, 'id');

        // 3. 查找关联的礼品活动（只取进行中的活动）
        $giftsQuery = Db::name('gifts')
            ->alias('g')
            ->field('g.id,g.pro_id,g.name,g.start_time,g.end_time')
            ->whereIn('g.pro_id', $proIds)
            ->where('g.status', 1)
            ->where('g.start_time', '<=', time())
            ->where('g.end_time', '>=', time());

        self::withTranslation($giftsQuery, 'g', $lang, ['name'], 'qiswl_gifts_trans', 'gift_id');
        $gifts = $giftsQuery->select();
        if ($gifts instanceof \think\Collection) {
            $gifts = $gifts->toArray();
        }

        // 以 pro_id 为 key
        $giftsByPro = [];
        foreach ($gifts as $gift) {
            $giftsByPro[$gift['pro_id']] = $gift;
        }

        // 4. 组装 gift 信息
        foreach ($lists as &$pro) {
            // 转换为浮点数
            $pro['price'] = (float)$pro['price'];
            $pro['ori_price'] = (float)$pro['ori_price'];

            if (isset($giftsByPro[$pro['id']])) {
                $gift = $giftsByPro[$pro['id']];
                $pro['gift'] = [
                    'id' => $gift['id'],
                    'name' => $gift['name'],
                    'start_time' => date('Y-m-d H:i:s', $gift['start_time']),
                    'end_time' => date('Y-m-d H:i:s', $gift['end_time']),
                ];
            } else {
                $pro['gift'] = null;
            }
        }

        return $lists;
    }
}
