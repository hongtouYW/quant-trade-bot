<?php

namespace app\index\controller;
use app\index\model\Notice as NoticeModel;
class Notice extends Base
{

    public  function lists(){
        $list = NoticeModel::lists($this->site);
        return show(1,$list);
    }

}