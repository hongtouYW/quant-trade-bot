<?php

namespace app\index\controller;

use app\index\model\H5Ads;
use app\index\model\PcAds;

class Ads extends Base
{

    /**
     * Notes:广告列表
     *
     * DateTime: 2024/7/2 下午3:40
     */
    public function lists(){
        $device = getDevice();
        switch ($device){
            case 2:
                $model = new PcAds();
                break;
            case 1:
            default:
                $model = new H5Ads();
                break;
        }
        return show(1,$model->lists($this->site));
    }

}