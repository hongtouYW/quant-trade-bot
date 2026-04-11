<?php

namespace app\index\controller;

use app\index\model\Articles;

class Review extends Base
{

    /**
     * Notes:文章列表
     *
     * DateTime: 2024/7/2 下午3:40
     */
    public function lists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):10;
        $lists = Articles::lists($page,$limit);
        return show(1,$lists);
    }

    public function info(){
        $id = getInput('id');
        $info = Articles::info($id);
        if(!$info){
            return show(3006);
        }

        return show(1,$info);

    }

}