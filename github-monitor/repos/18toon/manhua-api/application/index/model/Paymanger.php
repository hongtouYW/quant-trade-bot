<?php

namespace app\index\model;

use app\traits\HasTranslation;
use think\Model;

class Paymanger extends Model
{
    use HasTranslation;

    public static function lists($lang)
    {
        $query = self::alias('p')
            ->field('p.id,p.qudaopaydata,p.qudaoname,p.qudaomoney')
            ->where('qudaoswitch', 1)
            ->order('qudaosort');

        // 处理漫画和翻译
        self::withTranslation($query, 'p', $lang, ['qudaoname', 'qudaodes'], 'qiswl_paymanger_trans', 'gateway_id');

        $lists = $query->select();

        return $lists;
    }
}
