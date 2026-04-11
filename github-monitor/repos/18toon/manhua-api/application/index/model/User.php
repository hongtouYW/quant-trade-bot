<?php

namespace app\index\model;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Model;

class User extends Model
{
    const info_field = 'id,username,nickname,email,password,isvip_status,viptime,score,code,token,token_val,auto_buy,discount_time';
    protected $name = 'member';
    protected  $hidden = ['pid','last_time','status'];


    public function getIsvipStatusAttr($val,$data){
        $val = 0;
        $time = time();
        if($data['viptime'] > $time){
            $val = 1;
        }
        return $val;
    }

    public static function getUserInfo($id) {
        $user = self::field(self::info_field)->where('id', '=', $id)->find();
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }
    

    public function getNicknameStatusAttr($value, $data)
    {
        // 你可以根据自己的业务逻辑来判断昵称状态
        if (isset($data['nickname']) && !empty($data['nickname'])) {
            return 'valid';  // 示例：如果昵称存在且不为空，状态为 'valid'
        }
        return 'invalid'; // 否则，状态为 'invalid'
    }

    public function getAvatarStatusAttr($value, $data)
    {
        // 判断头像是否存在
        if (isset($data['avatar']) && !empty($data['avatar'])) {
            return 'valid';  // 示例：如果头像存在且不为空，状态为 'valid'
        }
        return 'invalid';  // 否则，状态为 'invalid'
    }

}