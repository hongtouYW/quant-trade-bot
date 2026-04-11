<?php

namespace app\index\controller;

use app\index\model\Banner as BannerModel;
use app\lib\exception\BaseException;

class Banner extends Base
{

    /**
     * Notes:广告
     *
     * DateTime: 2023/8/13 23:29
     */
    public function lists()
    {
        $position = !empty(getInput('position')) ? getInput('position') : 1;
        $positions = array_filter(explode(',', $position));
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = BannerModel::lists($positions, $lang);
        return show(1, $lists);
    }
}
