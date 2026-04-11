<?php

namespace app\index\controller;

use think\Controller;

class Test extends Controller
{
    public function test(){
        echo '10-16';
    }


    public function char(){

        $str = getRandChar(16);
        echo $str;
    }

    public function aes(){
        $timestamp = time();
        //$timestamp = 1698041325;
        $data = [
            'username'=>'a123456',
            'password'=>'123456',
            'timestamp'=>$timestamp,
        ];
        $str = build_link_string($data);
        $data['encode_sign'] = md5($str.config('lock.app_sign'));
        $iv = getRandChar(6);
        echo $iv;
        echo PHP_EOL;
        $data = apps_encrypt($data,$iv);
        print_r($data);die();
    }


    public function des(){
        $str = 'QHZcwmpUMrM4KNnQFtEMLtUPGiSNXJLoWun+1wWRgtv8XF+h4Mo2rAF4s9IdDfyLObz2woQwkwfa5dy1ngfF9hzAf5E3adYCDO4gt/8SyJiSdNt4hT0Zc2Tp0nxk4umgViWbC3zMUvavpULWuvM1aMl8zIgZXzoeL1Le7TDaTI4=';
        $iv_suffix = 'YAGkAk';
        $decrypt = apps_decrypt($str,$iv_suffix);
        print_r($decrypt);die();
    }

}