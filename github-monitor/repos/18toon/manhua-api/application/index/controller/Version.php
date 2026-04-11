<?php

namespace app\index\controller;

class Version extends Base
{
    /**
     * Notes:版本更新
     *
     * DateTime: 2023/9/11 17:49
     */
    public function index(){

        $type = !empty(getInput('type'))?getInput('type'):1;
        $info = \app\index\model\Version::getLast($type);
        return show(1,$info);

    }
}