<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/14
 * Time: 22:10
 */

namespace app\index\model;

class LoginAdmin extends BaseModel
{
    protected $table="login_admin";
    public function getAdminNameAttr($value,$data){

        return model('admin')->where('id','=',$data['admin_id'])->value('username');
    }
}