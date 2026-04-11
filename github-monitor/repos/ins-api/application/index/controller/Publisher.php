<?php

namespace app\index\controller;
use app\index\model\Publisher as PublisherModel;
class Publisher extends Base
{
    /**
     * Notes:片商列表
     *
     * DateTime: 2024/7/2 下午3:40
     */
    public function lists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $keyword = getInput('keyword');
        $lists = PublisherModel::lists($page,$limit,$this->site,$keyword);
        return show(1,$lists);
    }


}