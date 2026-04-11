<?php

namespace app\index\controller;
use app\index\model\Notice as NoticeModel;
class Notice extends Base
{

    public  function lists(){
        $list = NoticeModel::lists();
        return show(1,$list);
    }

}