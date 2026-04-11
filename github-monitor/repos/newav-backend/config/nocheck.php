<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/18
 * Time: 11:26
 */


return [
    /***
     * 跳过权限验证方法列表
     ***/
    '/index/index',
    '/index/welcome',  //主页
    '/login/login', //登录
    '/login/loginout', //退出
    '/base/clear',  //清除缓存
    '/base/clearredis',
    '/config/info'
];
