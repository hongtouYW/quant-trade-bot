<?php

namespace app\index\controller;
use app\index\model\Banner as BannerModel;
use app\index\model\Banner1 as Banner1Model;
use app\index\model\Banner2 as Banner2Model;
use app\index\model\Banner3 as Banner3Model;

class Banner extends Base
{

    /**
     * Notes:广告
     *
     * DateTime: 2023/8/13 23:29
     */
    public function lists(){
        switch ($this->site){
            case 1:
                $lists = BannerModel::lists();
                break;
            case 2:
                $lists = Banner1Model::lists();
                break;
            case 3:
                $lists = Banner2Model::lists();
                break;
            case 4:
                $lists = Banner3Model::lists();
                break;
        }
        return show(1,$lists);
    }
}