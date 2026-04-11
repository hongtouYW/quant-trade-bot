<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/4/29
 * Time: 3:11
 */

namespace app\index\controller;
use app\index\model\Config as ConfigModel;
use app\index\model\HelpCenter;

class Config extends Base
{

    //配置列表
    public function lists()
    {

        $list = ConfigModel::lists();
        return show(1,$list);
    }


    //帮助中心
    public function helpcenter(){

        try
        {
            $list = HelpCenter::lists();
        }
        catch (\Exception $e)
        {
            return show(0);
        }

        return show(1,$list);

    }

}