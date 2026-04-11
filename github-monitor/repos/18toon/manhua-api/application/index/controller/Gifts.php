<?php

namespace app\index\controller;

use app\index\model\Gifts as GiftsModel;

class Gifts extends Base
{
    /**
     * Notes: 获取推广活动
     * 用于第一次打开网站时，判断是否有活动需要弹出
     *
     * DateTime: 2025/09/30
     */
    public function promotion()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';

        $gift = GiftsModel::promotion($lang);

        if (!$gift) {
            return show(1, null, '暂无推广活动');
        }

        $data = [
            'id'          => $gift['id'],
            'name'        => $gift['name'],
            'start_time'  => date('Y-m-d H:i:s', $gift['start_time']),
            'end_time'    => date('Y-m-d H:i:s', $gift['end_time']),
        ];

        return show(1, $data);
    }
}
