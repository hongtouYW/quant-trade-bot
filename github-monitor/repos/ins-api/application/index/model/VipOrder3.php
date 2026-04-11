<?php

namespace app\index\model;

use think\Model;

class VipOrder3 extends Model
{

    protected $table = 'vip_order_dm';

    public function getAddTimeAttr($value)
    {
        return date('Y-m-d H:i:s',$value);
    }

    public function getTitleAttr($val,$data)
    {
        $vipInfo =  Vip3::field('title,title_en,title_ru')->where('id',$data['vid'])->find();
        return $vipInfo['title'];
    }


    public static function lists($uid,$page,$limit){

        return self::field('title,money,vid,order_sn,add_time,status')->where('uid',$uid)->order('id','desc')->paginate([
            'list_rows' => $limit,
            'page'     => $page,
        ])->toArray();
    }
}