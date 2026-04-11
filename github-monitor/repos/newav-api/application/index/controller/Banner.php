<?php

namespace app\index\controller;
use app\index\model\Banner as BannerModel;

class Banner extends Base
{

    /**
     * Notes:广告
     *
     * DateTime: 2023/8/13 23:29
     */
    public function lists(){
        $lists = BannerModel::lists();
        return show(1,$lists);
    }
}