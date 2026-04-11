<?php
namespace app\index\controller;

use app\index\model\Tags;
use app\index\model\Notice;
use app\index\model\Version;
use app\lib\exception\BaseException;
use think\facade\Request;

class Index extends Base
{

    public function index(){
        return show(1);
    }

}
