<?php
namespace app\index\controller;
use app\index\model\Order as OrderModel;
use app\index\model\User as UserModel;
use think\Db;
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
     *
     */
    public function welcome(){

        $userModel = new UserModel();
        $orderModel = new OrderModel();


        $today_order = $orderModel::whereTime('addtime2','today')->whereRaw('is_kl = :is_kl OR bounded = :bounded ', ['is_kl' => 0, 'bounded' => 1])
            ->count();
        $today_pay = $orderModel::whereTime('addtime2','today')->where('is_kl','=',0)->where('orderswitch','=',1)->count();
        $today_profit = $orderModel::whereTime('addtime2','today')->where('is_kl','=',0)->where('orderswitch','=',1)->sum('money');
        $today_discount= $orderModel::whereTime('addtime2','today')->where('discount','=',1)->where('pro_id','=',3)->where('is_kl','=',0)->where('orderswitch','=',1)->count();
        $today_user = $userModel::whereTime('register_time','today')->count();
        $today_view = Db::name('history')->whereTime('addtime', 'today')->count();

        $this->assign([
            'today_order'=>$today_order,
            'today_pay'=>$today_pay,
            'today_profit'=>$today_profit,
            'today_discount'=>$today_discount,
            'today_user'=>$today_user,
            'today_view'=>$today_view
        ]);


        $yes_order = $orderModel::whereTime('addtime2','yesterday')->whereRaw('is_kl = :is_kl OR bounded = :bounded ', ['is_kl' => 0, 'bounded' => 1])
            ->count();
        $yes_pay = $orderModel::whereTime('addtime2','yesterday')->where('is_kl','=',0)->where('orderswitch','=',1)->count();
        $yes_profit = $orderModel::whereTime('addtime2','yesterday')->where('is_kl','=',0)->where('orderswitch','=',1)->sum('money');
        $yes_discount= $orderModel::whereTime('addtime2','yesterday')->where('discount','=',1)->where('pro_id','=',3)->where('is_kl','=',0)->where('orderswitch','=',1)->count();
        $yes_user = $userModel::whereTime('register_time','yesterday')->count();
        $yes_view = Db::name('history')->whereTime('addtime', 'yesterday')->count();

        $this->assign([
            'yes_order'=>$yes_order,
            'yes_pay'=>$yes_pay,
            'yes_profit'=>$yes_profit,
            'yes_discount'=>$yes_discount,
            'yes_user'=>$yes_user,
            'yes_view'=>$yes_view,
        ]);

        //当前日期
        $ymd=date("Y-m-d");
        $this->assign("ymd",$ymd);
        return $this->fetch('welcome');
    }

}
