<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\Model;

class Order extends Model
{
    use HasTranslation;

    public static function lists($uid, $page, $limit, $lang)
    {
        $orders = self::where('member_id', '=', $uid)
            ->order('id desc')
            ->paginate([
                'list_rows' => $limit,
                'page' => $page
            ]);

        $orders->each(function ($item) use ($lang) {
            $proQuery = Pro::alias('p')->where('p.id', $item['pro_id']);
            self::withTranslation($proQuery, 'p', $lang, ['title', 'intro'], 'qiswl_pro_trans', 'product_id');
            $item['pro_info'] = $proQuery->find();

            $paymanagerQuery = Paymanger::alias('p')->where('p.id', $item['paydata']);
            self::withTranslation($paymanagerQuery, 'p', $lang, ['qudaoname', 'qudaodes'], 'qiswl_paymanger_trans', 'gateway_id');
            $item['pay_info'] = $paymanagerQuery->find();
        });

        return $orders;
    }
}
