<?php

namespace app\index\model;

use think\Model;

class Feedback extends Model
{


    protected $hidden = ['member_id'];

    public static function lists($uid,$page,$limit){
        $lists = self::where('member_id','=',$uid)->order('id desc')->paginate([
            'list_rows' => $limit,
            'page'     => $page,
        ]);
        return $lists;
    }

}