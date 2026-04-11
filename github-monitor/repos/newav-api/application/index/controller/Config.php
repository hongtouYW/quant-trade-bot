<?php
namespace app\index\controller;
use app\index\model\Configs;
use app\index\model\Links;

class Config extends Base
{

    /**
     * Notes:获取配置列表
     *
     * DateTime: 2022/4/27 14:48
     */
    public function lists()
    {

        $list = Configs::lists();
        return show(1,$list);
    }


    /**
     * Notes:获取配置
     *
     * DateTime: 2022/4/27 14:48
     */
    public function tip()
    {
        $lang = getLang();
        $key = ['zh'=>'notice','en'=>'notice_en','ru'=>'notice_ru'];
        $value = Configs::get($key[$lang]);
        return show(1,$value);

    }
    /**
     * Notes:友情链接
     *
     * DateTime: 2022/4/27 14:48
     */
    public function links(){

        $lists = Links::lists();
        return show(1,$lists);
    }

}