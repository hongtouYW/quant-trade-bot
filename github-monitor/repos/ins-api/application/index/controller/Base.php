<?php
namespace app\index\controller;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\facade\Request;
use think\Controller;

class Base extends Controller
{
    public $redis = '';
    protected $site;
    public function initialize()
    {
        $this->redis = new Redis();
        $this->site = getSite();
        $controller = strtolower(Request::controller());
        $action = strtolower(Request::action());

        if (config('lock.app_lock') &&  (!in_array($controller.'/'.$action, config('ignore.'))))
        {
            if(!checkSign()) throw new BaseException(1001);
            $time=getInput('timestamp');
            if(strlen(strval($time))==13){
                $time=$time/1000;
            }
            if((time()-$time)>300) throw new BaseException(1002);
        }
        parent::initialize();
    }

}