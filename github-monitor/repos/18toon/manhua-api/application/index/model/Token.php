<?php
namespace app\index\model;
use app\lib\exception\BaseException;
use think\Model;
class Token extends Model
{
    // 生成令牌
    public static function generateToken()
    {
        $randChar = getRandChar(32);
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        $tokenSalt = config('lock.app_token_key');
        return md5($randChar . $timestamp . $tokenSalt);
    }

    // 根据token获取uid
    public static function getCurrentUid(){

        $token = getInput('token');
        if(!$token){
            throw new BaseException(2000);
        }
        $user = User::field('id,status')->where('token_val','>',time())->where('token','=',$token)->find();
        if(!$user){
            throw new BaseException(2000);
        }
        if($user['status'] == 0){
            throw new BaseException(2010);
        }
        $time = time();
        $data = [
            'token_val'=>$time+config('lock.app_token_val'),
            'last_time'=>$time
        ];
        User::where('id','=',$user['id'])->update($data);
        return $user['id'];
    }



    // 根据token获取uid
    public static function getCurrentUid1(){

        $token = getInput1('token');
        if(!$token){
            throw new BaseException(2000);
        }
        $user = User::field('id,status')->where('token_val','>',time())->where('token','=',$token)->find();
        if(!$user){
            throw new BaseException(2000);
        }
        if($user['status'] == 1){
            throw new BaseException(2008);
        }

        $time = time();
        $data = [
            'token_val'=>$time+config('lock.app_token_val'),
            'login_time'=>$time
        ];
        User::where('id','=',$user['id'])->update($data);
        return $user['id'];
    }

}