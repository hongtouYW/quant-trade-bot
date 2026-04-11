<?php
namespace app\index\controller;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\facade\Request;
use think\Controller;

class Base extends Controller
{
    public $redis = '';
    public function initialize()
    {
        $this->redis = new Redis();
        $this->rateLimitCheck();
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
    
    protected function rateLimitCheck()
    {
        $ip       = get_client_ip();
        $endpoint = strtolower(Request::controller() . '/' . Request::action());
        $uid      = getUid() ?: 0;
        $params   = Request::param();

        // Optional: remove dynamic fields (signature/timestamp)
        // unset($params['sign'], $params['timestamp']);

        // Sort & hash params to keep key short
        ksort($params);
        $paramsHash = md5(json_encode($params, JSON_UNESCAPED_UNICODE));

        // Final redis key (unique by IP + UID + endpoint + params)
        $key = "ratelimit:{$ip}:{$uid}:{$endpoint}:{$paramsHash}";

        $cnt = $this->redis->inc($key);

        if ($cnt == 1) {
            $this->redis->expire($key, 10);
        }

        if ($cnt >= 5) {
            throw new BaseException(1004); // 操作过于频繁
        }
    }

}