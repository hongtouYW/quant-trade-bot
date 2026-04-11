<?php

namespace app\index\controller;

use app\index\model\Category as CategoryModel;
use app\index\model\Video;

class Category extends Base
{
    public function lists(){
        $page    = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $lists   = CategoryModel::lists($page,$limit);
        return show(1,$lists);
    }
}