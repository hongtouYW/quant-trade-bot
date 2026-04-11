<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/14
 * Time: 20:09
 */

namespace app\index\controller;

use app\extra\PHPGangsta\GoogleAuthenticator;
use app\index\model\Admin;
use think\cache\driver\Redis;
use think\Controller;
use think\facade\Config;
class Login extends Controller
{
    public function index()
    {
        writelog("调用日志");
    }




    public function clearRedis() {
        $redis = new Redis();
        $redis->clear();
        exit('清除Redis缓存成功');
    }


    /**
     * Notes:登录
     * User: joker
     * Date: 2022/1/15
     * Time: 16:54
     * @return mixed|\think\response\Json|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function login()
    {
        //判断是session中是否有值
        if(\session('admin_id')){
            $this->redirect('/index/index');
        }else{
            if (\request()->isPost()){
                $username=input("username".'');
                $pwd=input("pwd",'');
                $pwd=pswCrypt($pwd);
                $code = input('code', '');
                //验证用户是否填写
                if (empty($username)||empty($pwd)){
                    return json(["code"=>0,"msg"=>"用户名或密码不能为空"]);
                }
                $model = new Admin();
                $adminInfo=$model->where(array("username"=>$username))->find();
                //验证用户是否存在
                if(empty($adminInfo)){
                    return json(["code"=>0,"msg"=>"当前用户不存在或者用户名错误"]);
                }
                //验证密码
                if($pwd!=$adminInfo["password"]){
                    return json(["code"=>0,"msg"=>"密码错误请从新输入"]);
                }
                //验证用户的状图
                if ($adminInfo["status"]==2){
                    return json(["code"=>0,"msg"=>"当前用户已经被冻结，请联系管理员"]);
                }

                 $isGoogleCheck = Config::get('app.google_check');
                 if($isGoogleCheck){
                     if(empty($code)){
                         return json(["code"=>0,"msg"=>"请输入谷歌验证码"]);
                     }
                     $ga = new GoogleAuthenticator();
                     $checkResult = $ga->verifyCode($adminInfo['google_secret'], $code, 1);
                     if(!$checkResult){
                         return json(["code"=>0,"msg"=>"谷歌验证码错误或失效"]);
                     }
                 }
                //存sessiopn
                cookie('admin_id',$adminInfo['id'],3600*24*3);
                session('admin_id', $adminInfo["id"]);
                session('admin_name', $adminInfo["username"]);

                //记录时间及ip
                $model->where(array('id'=>$adminInfo['id']))->update(['last_login'=>date('Y-m-d H:i:s', time()), 'last_ip'=>request()->ip()]);
                //登录日志的记录
                $data["admin_id"]=$adminInfo["id"];
                $data["ip"]=request()->ip();
                $data["browser_type"]=get_broswer_type();
                $data["browser"]=get_broswer();
                $data["create_time"]=date('Y-m-d H:i:s', time());
                $data["type_os"]=get_os();
                model('login_admin')->insert($data);

                $this->success("登录成功");
            }else{
                $isGoogleCheck = Config::get('app.google_check');
                $this->assign('isGoogleCheck',$isGoogleCheck);
                return $this->fetch("login");
            }
        }
    }

    /**
     * Notes:谷歌验证码
     * User: joker
     * Date: 2022/1/15
     * Time: 16:54
     * @param $secret
     * @param $code
     * @return bool
     *
     */
    public function googleCheckCode($secret,$code){
        $ga = new GoogleAuthenticator();
        $checkResult = $ga->verifyCode($secret, $code, 1);
        if ($checkResult) {
            session('googleSecret', $secret);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Notes:退出登录
     * User: joker
     * Date: 2022/1/15
     * Time: 16:53
     */
    public function loginout(){
        session('admin_id', null);
        $this->redirect('login/login');
    }

}