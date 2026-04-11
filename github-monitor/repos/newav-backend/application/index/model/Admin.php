<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/14
 * Time: 22:07
 */

namespace app\index\model;

class Admin extends BaseModel
{


    protected $table="admin";

    public function getRoleNameAttr($value,$data){

        return model('auth_role')->where('role_id','=',$data['role_id'])->value('role_name');
    }





}