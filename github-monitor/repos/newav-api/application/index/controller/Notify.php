<?php

namespace app\index\controller;
use app\index\model\Configs;
use app\index\model\Tongji;
use app\index\model\VipOrder;
use app\index\model\User as UserModel;
use app\index\model\VipOrder2 as WumaVipOrder;
use app\index\model\VipOrder3 as VipOrderDm;
use app\index\model\VipOrder4 as VipOrder4k;
use think\Db;
use think\Controller;

class Notify extends Controller
{

    /**
     * Notes:
     *
     * DateTime: 2024/7/9 下午4:30
     */
    public function callbackwxr(){
        if (Request()->isPost()) {
            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackwxr");

            //初始化
            $order_no   = $result["orderId"]; //系统订单号
            $invoice_no = $result['sourceId']; //盈利订单号
            $status     = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount     = $result["amount"];

            if ($status == '1') {
                $res = $this->doCallback($amount,$order_no, $invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackwxr_success');
                } else {
                    save_log($res,'callbackwxr_error');
                }
            }
            //支付成功或失败 都返回OK
            echo "OK";
            exit;
        }
    }

    public function successwxr()
    {
        //记录LOG
        //转跳至前端
        $jsondata = input('get.');
        save_log($jsondata, "successwxr");

        echo "success";
        //$return_url = ""; //需要前端给我 再hardcode进来
        //header('location: '.$return_url);
    }

    public function callbackgz(){
        if (Request()->isPost()) {
            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackgz");

            $order_no   = $result["order_no"]; //系统订单号
            $invoice_no = $result["invoice_no"]; //唐朝订单号
            $status     = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount     = $result["amount"];
            // $custom_column = isset($result['custom_column'])?$result['custom_column']:'';
            if ($status == '1') {
                $res = $this->doCallback($amount,$order_no, $invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackgz_success');
                } else {
                    save_log($res,'callbackgz_error');
                }
            }
            echo "OK";
            exit;
        }
    }

    public function successgz()
    {
        //记录LOG
        //转跳至前端
        $jsondata = input('get.');
        save_log($jsondata, "successgz");

        echo "success";
        //$return_url = ""; //需要前端给我 再hardcode进来
        //header('location: '.$return_url);
    }

    private function doCallback($amount, $order_no, $invoice_no){
        $time = time();
        $result = ['status'=>0,'msg'=>'网络繁忙，请稍后再试','order_no'=>$order_no];
        $orderInfo = VipOrder::where('order_sn','=',$order_no)->find();
        if(!$orderInfo){
            $result['msg'] =  '订单不存在';
            return $result;
        }
        if($orderInfo['status'] == 1){
            $result['msg'] = '已经支付过了~';
            return $result;
        }
        if((int)$amount != (int)$orderInfo['money']){
            $result['msg'] = '订单金额与支付金额不一致';
            return $result;
        }
        $order = [
            'pay_code' => $invoice_no,
            'status'   => 1,
            'pay_time' => $time
        ];
        Db::startTrans();
        $is_up = VipOrder::where('id','=',$orderInfo['id'])->update($order);
        if(!$is_up){
            Db::rollback();
            $result['msg'] = '修改订单状态失败';
            return $result;
        }

        $userInfo = UserModel::field('id,coin,point,vip_end_time,p')->where('id','=',$orderInfo['uid'])->find();

        if(!$userInfo){
            Db::rollback();
            $result['msg'] = '回调用户不存在';
            return $result;
        }

        // other package may bonus vip day
        if($orderInfo['day'] > 0){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
        }

        if($orderInfo['diamond'] > 0){
            $user['coin'] = $userInfo['coin']+$orderInfo['diamond'];
        }

        if($orderInfo['point'] > 0){
            $user['point'] = $userInfo['point']+$orderInfo['point'];
        }

        if($orderInfo['is_kl'] == 1){
            $this->handleOrder($orderInfo['money']);
            if(!empty($userInfo['p'])){
                $this->callCpa($userInfo['id'],$userInfo['p'],$orderInfo['money']);
            }
        }

        $up_user = UserModel::where('id', '=', $userInfo['id'])->update($user);
        if(!$up_user){
            Db::rollback();
            $result['msg'] = '修改用户失败';
            return $result;
        }
        Db::commit();
        $result['status'] = 1;
        $result['msg']    = '支付成功';
        return $result;
    }

    //支付cpa
    private function callCpa($uid,$p,$money){
        $cpa = [
            'u_id'=>$uid,
            'p'=>$p,
            'charge_num'=>1,
            'charge_money'=>(int)$money
        ];
        $cpa_url = Configs::get('cpa_url_charge');
        $res = httpPost($cpa_url,$cpa);
        save_log($res,'callCpa-'.$p);
    }

    private function handleOrder($amount){

        $date = date('Y-m-d');
        $is_set = Tongji::where('date','=',$date)->value('id');
        if($is_set){
            $update = [
                'charge_num'=>Db::raw('charge_num+1'),
                'charge_sum'=>Db::raw('charge_sum+'.$amount)
            ];
            Tongji::where('id','=',$is_set)->update($update);
        }else{
            $add = [
                'date'=>$date,
                'charge_num'=>1,
                'charge_sum'=>$amount
            ];
            Tongji::insert($add);
        }

    }

}