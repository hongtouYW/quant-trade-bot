<?php

namespace app\index\controller;
use think\cache\driver\Redis;
use app\index\model\Hotlist as HotlistModel;
use app\index\model\User as UserModel;
use app\index\model\Token;
use think\Db;
use app\lib\exception\BaseException;

class Hotlist extends Base
{
    const list_limit = 20;
    /**
     * Notes:热榜
     *
     * DateTime: 2023/8/13 23:29
     */
    public function lists(){
        $page         = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit        = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $keyword      = getInput('keyword');
        $lists        = HotlistModel::lists($page, $limit, $keyword);
        return show(1,$lists);
    }

    public function details(){
        $hid  = HotlistModel::getHid();
        $info = HotlistModel::info($hid)->toArray();
        return show(1,$info);
    }
}