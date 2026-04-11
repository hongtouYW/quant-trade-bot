<?php

namespace app\service;

use think\Db;
use app\index\model\User as UserModel;

class GiftService
{
    /**
     * 检查并处理赠品活动
     *
     * @param array $orderInfo 订单信息
     * @param array $prodata   产品信息
     * @param int   $userId    用户ID
     * @return void
     */
    public static function checkGiftActivity(array $orderInfo, array $prodata, int $userId): bool
    {
        $now = time();

        // 查找是否有活动
        $proId = $orderInfo['pro_id'] ?? 0;
        if (!$proId) {
            return false;
        }

        $gift = Db::name('gifts')
            ->where('pro_id', $proId)
            ->where('status', 1)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->find();

        if (!$gift) {
            return false;
        }
        // 检查是否已经送过
        $exists = Db::name('gift_logs')->where('order_id', $orderInfo['id'])->count();
        if ($exists) {
            return false;
        }

        // 获取赠品明细
        $items = Db::name('gift_items')
            ->where('gift_id', $gift['id'])
            ->where('status', 1)
            ->select();
        foreach ($items as $item) {
            switch ($item['pro_type']) {
                case 'coin': // 金币
                    UserModel::where('id', $userId)->setInc('score', intval($item['value']));
                    break;

                case 'vip': // VIP天数
                    $user = UserModel::field('id,viptime')->find($userId);
                    $vipBaseTime = max($user['viptime'], $now);
                    $extraDays = intval($item['value']);
                    $newVipTime = $vipBaseTime + $extraDays * 86400;
                    UserModel::where('id', $userId)->update(['viptime' => $newVipTime]);
                    break;
            }
        }

        // 写日志，避免重复发放
        Db::name('gift_logs')->insert([
            'order_id' => $orderInfo['id'],
            'gift_id' => $gift['id'],
            'create_time' => $now,
            'update_time' => $now,
        ]);

        return true;
    }
}
