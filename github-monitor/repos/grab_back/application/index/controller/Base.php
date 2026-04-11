<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/14
 * Time: 22:30
 */

namespace app\index\controller;

use app\index\model\Authrole;
use think\cache\driver\Redis;
use think\Controller;
use think\facade\Config;

class Base extends Controller
{
    /*
     * 初始化操作
     */
    protected $adminInfo = null;
    protected $currencyList = null;
    public function initialize()
    {
        $admin_id=session('admin_id');
        if (empty($admin_id)){
            $this->redirect("login/login");
        }else{
            $this->adminInfo=model('admin')
                ->where(array("id"=>$admin_id))
                ->find();
        }

        if(!($this->check_auth())){
            if (request()->isPost()){
                $this->error('你没有该操作权限');
            }else{
                $html=<<<HTML
                <span style='text-align: center;color: red;margin-top:30%;margin-left: 50%'>你没有该操作权限!</span>
HTML;
                echo $html;
                exit;

            }
        }

        //取出角色
        $roleModel = new Authrole();
        $role=$roleModel
            ->where('role_id',$this->adminInfo["role_id"])
            ->field('role_name')
            ->find();

        //获取当前用户可以访问的菜单
        $menuInfo=$roleModel->getMenuInfo($this->adminInfo['id'],$this->adminInfo["role_id"]);
        $this->assign('menuInfo',$menuInfo);  //读取当前管理员的所有菜单栏的列表
        $this->assign('role',$role);  //读取角色的信息
        $this->assign('admin',$this->adminInfo["username"]);   //读取当前管理员的信息
        $this->assign('adminInfo',$this->adminInfo);//读取当前管理员的信息
        parent::initialize();
    }


    /**
     * 判断权限
     */

    public function check_auth(){

        if($this->adminInfo['id'] == 1){
            return true;
        }
        $route=$this->getRoute(); //获取当前的操作的路由
        if (in_array($route,Config::get('nocheck.'))){
            return true;
        }
        $auth=model('authrole')->getAuthInfo($this->adminInfo['role_id']);
        if ($auth==NULL){
            return false;
        }

        if (in_array($route,$auth)){
            return true;
        }
    }


    private function getRoute()
    {
        $controller = \think\facade\Request::instance()->controller();
        $action = \think\facade\Request::instance()->action();
        return '/' . strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $controller)) . '/' . $action;
    }


    /**
     * 清除缓存
     *
     */
    public function clear() {
        $CACHE_PATH = config('cache.runtime_path').'/cache/';
        $TEMP_PATH = config('cache.runtime_path').'/temp/';

/*        $LOG_PATH = config('cache.runtime_path').'/log/';
        if (delete_dir_file($CACHE_PATH) && delete_dir_file($TEMP_PATH) && delete_dir_file($LOG_PATH)) {
            $this->success('清除缓存成功!');
        } else {
            $this->error('清除缓存失败!');
        }*/
        delete_dir_file($CACHE_PATH) && delete_dir_file($TEMP_PATH);
        $this->success('清除缓存成功!');
    }

    public function clearRedis() {
        $redis = new Redis();
        $redis->clear();
        $this->success('清除Redis缓存成功!');
    }


    /**
     * 严格校验接口是否为POST请求
     */
    protected function checkPostRequest(){
        if (!$this->request->isPost()) {
            $this->error("当前请求不合法！");
        }
    }
}