<?php

namespace app\index\model;

use think\Model;

class VipOrder extends Model
{

    public function getAddTimeAttr($value)
    {
        return date('Y-m-d H:i:s',$value);
    }

    public function getTitleAttr($val,$data)
    {
        switch ($data['product_type']){
            case 1:
                $vipInfo = Vip::info($data['product_id']);
                break;
            case 2:
                $vipInfo = Point::info($data['product_id']);
                break;
            case 3:
                $vipInfo = Coin::info($data['product_id']);
                break;
        }
        // $vipInfo =  Vip::field('title,title_en,title_ru')->where('id',$data['vid'])->find();
        return $vipInfo['title'];
    }

    public static function lists($uid,$page,$limit){
        return self::field('title,money,product_id,product_type,diamond,point,day,order_sn,add_time,status')
            ->where('uid',$uid)
            ->order('id','desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
        ])->toArray();
    }
}