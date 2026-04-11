<?php

namespace app\index\model;

use think\Model;

class Domain extends Model
{

    protected $table= 'domain';
    public static function lists(){

        $lists = self::order('id asc')->column('url');
        return $lists;
    }
}