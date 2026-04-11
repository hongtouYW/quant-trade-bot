<?php

namespace app\index\controller;

use app\index\model\Tags;
use app\index\model\Video;

class Tag extends Base
{
    /**
     * Notes:获取标签列表
     *
     * DateTime: 2022/4/27 14:48
     */
    public function lists(){

        $page    = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $top     = request()->has('top') ? getInput('top') : null;
        $keyword = getInput('keyword');
        $lists   = Tags::lists($page,$limit,$keyword, $top);

        return show(1,$lists);
    }

    public function homeList()
    {
        $page    = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $lists   = Tags::homeList($page,$limit);
        return show(1,$lists);
    }
}