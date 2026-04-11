<?php

namespace app\index\controller;

use app\index\model\Ads as AdsModel;

class Ads extends Base
{
    public function lists()
    {
        $lists = AdsModel::lists();
        return show(1, $lists);
    }
}
