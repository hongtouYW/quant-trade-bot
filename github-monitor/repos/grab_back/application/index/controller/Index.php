<?php
namespace app\index\controller;

use app\index\model\VipOrder;
use think\facade\App;
use app\index\model\User as UserModel;

class Index extends Base
{
    //主页
    public function index(){
        return $this->fetch("index");
    }

    /**
     * Notes:欢迎页
     * User: joker
     * Date: 2022/1/15
     * Time: 16:55
     * @return mixed
     */
    public function welcome(){

        // 系统
        $systemInfo['os'] = PHP_OS;
        //ThinkPHP 版本
        $systemInfo['ThinkPHP'] = App::version();
        // PHP版本
        $systemInfo['phpversion'] = PHP_VERSION;
        // 最大上传文件大小
        $systemInfo['maxuploadfile'] = ini_get('upload_max_filesize');
        // 脚本运行占用最大内存
        $systemInfo['memorylimit'] = get_cfg_var("memory_limit") ? get_cfg_var("memory_limit") : '-';
        //当前的IP
        $systemInfo['REMOTE_ADDR']=$_SERVER['REMOTE_ADDR'];

        $userModel        = new UserModel();
        $orderModel       = new VipOrder();
        
        // Get table names
        $userTable = $userModel->getTable();
        $orderTable = $orderModel->getTable();
        
        // TODAY - Non-agent users (orders, payments, profits)
        $today_order      = $orderModel::whereTime('add_time','today')
            ->where('is_kl','=',0)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->count();
        
        $today_pay        = $orderModel::whereTime('pay_time','today')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->count();
        
        $today_profit     = $orderModel::whereTime('pay_time','today')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->sum('money');

        // TODAY - Agent users (orders, payments, profits)
        $today_agent_order = $orderModel::whereTime('add_time','today')
            ->where('is_kl','=',0)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->count();
        
        $today_agent_pay = $orderModel::whereTime('pay_time','today')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->count();
        
        $today_agent_profit = $orderModel::whereTime('pay_time','today')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->sum('money');

        // User counts
        $today_user       = $userModel::whereTime('reg_time', 'today')->where('agent_code', '')->count();
        $today_agent_user = $userModel::whereTime('reg_time', 'today')->where('agent_code', '<>', '')->count();

        // YESTERDAY - Non-agent users
        $yes_order      = $orderModel::whereTime('add_time','yesterday')
            ->where('is_kl','=',0)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->count();
        
        $yes_pay        = $orderModel::whereTime('pay_time','yesterday')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->count();
        
        $yes_profit     = $orderModel::whereTime('pay_time','yesterday')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '');
            })
            ->sum('money');

        // YESTERDAY - Agent users
        $yes_agent_order = $orderModel::whereTime('add_time','yesterday')
            ->where('is_kl','=',0)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->count();
        
        $yes_agent_pay = $orderModel::whereTime('pay_time','yesterday')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->count();
        
        $yes_agent_profit = $orderModel::whereTime('pay_time','yesterday')
            ->where('is_kl','=',0)
            ->where('status','=',1)
            ->whereExists(function($query) use ($userTable, $orderTable) {
                $query->table($userTable)
                    ->where($userTable.'.id = '.$orderTable.'.uid')
                    ->where('agent_code', '<>', '');
            })
            ->sum('money');

        // User counts for yesterday
        $yes_user       = $userModel::whereTime('reg_time','yesterday')->where('agent_code', '')->count();
        $yes_agent_user = $userModel::whereTime('reg_time','yesterday')->where('agent_code', '<>', '')->count();

        // Assign variables to view
        $this->assign('today_order',$today_order);
        $this->assign('today_pay',$today_pay);
        $this->assign('today_profit',$today_profit);
        $this->assign('today_user',$today_user);

        $this->assign('today_agent_order',$today_agent_order);
        $this->assign('today_agent_pay',$today_agent_pay);
        $this->assign('today_agent_profit',$today_agent_profit);
        $this->assign('today_agent_user',$today_agent_user);

        $this->assign('yes_order',$yes_order);
        $this->assign('yes_pay',$yes_pay);
        $this->assign('yes_profit',$yes_profit);
        $this->assign('yes_user',$yes_user);
        
        $this->assign('yes_agent_order',$yes_agent_order);
        $this->assign('yes_agent_pay',$yes_agent_pay);
        $this->assign('yes_agent_profit',$yes_agent_profit);
        $this->assign('yes_agent_user',$yes_agent_user);

        //当前日期
        $ymd=date("Y-m-d");
        $this->assign("ymd",$ymd);
        $this->assign("systemInfo",$systemInfo);
        return $this->fetch('welcome');
    }
}