<?php

namespace app\index\controller;

use app\index\model\Cats;
use app\index\model\Notice;
use app\index\model\Domain;
use app\index\model\Links;

class Index extends Base
{

    public function index()
    {
        echo '6-6-2';
    }

    /**
     * Notes:公告
     *
     * DateTime: 2023/8/7 18:21
     */
    public function notice()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $list = Notice::lists($lang);
        return show(1, $list);
    }


    /**
     * Notes:域名链接
     *
     * DateTime: 2023/8/7 18:21
     */
    public function domain()
    {

        $list = Domain::lists();
        return show(1, $list);
    }

    /**
     * Notes:友情连接
     *
     * DateTime: 2023/12/22 16:37
     */
    public function links()
    {

        $list = Links::lists();
        return show(1, $list);
    }
}
