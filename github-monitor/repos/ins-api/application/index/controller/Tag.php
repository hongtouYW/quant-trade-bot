<?php

namespace app\index\controller;

use app\index\model\Tag2;
use app\index\model\Tag4k;
use app\index\model\Tags;

class Tag extends Base
{
    /**
     * Notes:获取标签列表
     *
     * DateTime: 2022/4/27 14:48
     */
    public function lists(){

        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $keyword = getInput('keyword');

        switch ($this->site){
            case 1:
            case 2:
                $lists = Tags::lists($page,$limit,$keyword,$this->site);
                break;
            case 3:
                $lists = Tag2::lists($page,$limit,$keyword);
                break;
            case 4:
                $lists = Tag4k::lists($page,$limit,$keyword);
                break;
        }

        return show(1,$lists);
    }

}