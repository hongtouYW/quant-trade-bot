<?php
namespace app\index\model;
use think\Model;
use app\lib\exception\BaseException;

class Upload extends Model
{
    /**
     * Notes:单图上传
     *
     * User: Jiezhu
     * DateTime: 2022/4/21 20:01
     */
    public static function uploadOne($save_path,$file){

        $file = request()->file($file);
        if(!$file){
            throw new BaseException(6001);
        }
        $upload_path = 'uploads/'.$save_path.'/' . date('Ymd', time()) . '/';
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->rule(function ($file) {
            return  md5(mt_rand()); // 使用自定义的文件保存规则
        })->move($upload_path);

        if($info){
            $file_name = str_replace("\\", '/', $info->getSaveName());
            $return_url = $upload_path.$file_name;
            return '/'.$return_url;
        }else{
            throw new BaseException(6002);
        }
    }
}