<?php

namespace app\index\controller;

use app\index\model\Token;
use think\cache\driver\Redis;
use think\Controller;

class Test extends Controller
{
    public function async(){
        echo '12-13-1';
    }


    public function clearRedis(){

        $redis = new Redis();
        $redis->clear();
        echo 'clear redis success';

    }


    public function aes(){
        $timestamp = time();
        $data = [
            'username'=>'nianshao100',
            'password'=>'123456',
            'timestamp'=>$timestamp,
        ];
        $str = build_link_string($data);
        $data['encode_sign'] = md5($str.config('lock.app_sign'));
        $iv = getRandChar(6);
        echo $iv;
        echo '<br>';
        $data = apps_encrypt($data,$iv);
        print_r($data);die();

    }

    public function des(){
        $str = 'BXS4VHfr7wmg+IbhrxvNHDfzyYGK13FIWIAAkNgvppagC7Rzw6V01s4T2N3HJblcpmlEnj7+RJeIsednwRUN5L6e9ObW6ZYJwkYNXfB13WpCRG1kOUNQSTg/qHbT/pxcqHGmqCsolcQDXwk7MVTXJFgvfOYDzy6bJCR//rrHi+E=';
        $iv_suffix = 'txBy2w';
        $decrypt = apps_decrypt($str,$iv_suffix);
        exit(json_encode($decrypt,JSON_UNESCAPED_UNICODE));
    }

    public static function getRandChar(){
        echo getRandChar(32);
    }

    public function db(){
        $data = db('user')->where('id',1)->find();
        print_r($data);
    }



    public function create_token(){

        exit('禁止访问');
        $min_id = (int)input('min_id');
        $max_id = $min_id + 10000;
        if (empty($min_id) || empty($max_id)){
            echo '参数错误';
            exit;
        }

        $user = \app\index\model\User::field('id,token')->where('id','between',[$min_id,$max_id])->where('token','=','')->select();

        foreach ($user as $v){
            $token = Token::generateToken();
            $res = \app\index\model\User::where('id',$v['id'])->update(['token'=>$token]);
            if ($res){
                echo '用户id:'.$v['id'].'生成token:'.$token.'成功<br>';
            }else{
                echo '用户id:'.$v['id'].'生成token失败<br>';
            }

        }
        exit('生成token成功');
    }
    
}