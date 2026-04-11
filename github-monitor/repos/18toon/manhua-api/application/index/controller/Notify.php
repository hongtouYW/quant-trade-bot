<?php

namespace app\index\controller;

use app\index\model\Order;
use app\index\model\Pro;
use app\index\model\User as UserModel;
use think\Controller;
use think\Db;
use think\facade\Request;

class Notify extends Controller
{


    public function callbackgz(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackgz");
            if (!$result['order_no']) {
                return json(['code' => 0, 'msg' => '缺失订单号']);
            }
            //初始化
            $order_no = $result["order_no"]; //系统订单号
            $invoice_no = $result["invoice_no"]; //唐朝订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];

            if ($status == '1') {

                $res = $this->doCallback($amount,$order_no,$invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackgz_success');
                } else {
                    save_log($res,'callbackgz_error');
                }

            }
            //支付成功或失败 都返回OK
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





    public function callbackwxr(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            if (!$result['orderId']) {
                return json(['code' => 0, 'msg' => '缺失订单号']);
            }
            save_log($result,"callbackwxr");

            //初始化
            $order_no = $result["orderId"]; //系统订单号
            $invoice_no = $result["sourceId"]; //系统订单号
            $amount = $result["amount"];
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败

            if ($status == '1') {

                $res = $this->doCallback($amount,$order_no,$invoice_no);
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


    //支付回调处理
    private function doCallback($amount,$order_no,$invoice_no){
        $time = time();
        $result = ['status'=>0,'msg'=>'网络繁忙，请稍后再试','order_no'=>$order_no];
        $orderInfo = Order::field('id,member_id,pro_id,orderswitch,money,discount')->where('ordernums','=',$order_no)->find();
        if(!$orderInfo){
            $result['msg'] =  '订单不存在';
            return $result;
        }
        if($orderInfo['orderswitch'] == 1){
            $result['msg'] = '已经支付过了~';
            return $result;
        }
        $prodata=Pro::field('type_status,addcoin,addvip')->where('id','=',$orderInfo['pro_id'])->find();
        if(!$prodata){
            $result['msg'] =  '充值商品不存在';
            return $result;
        }
        if((int)$amount*100 != (int)$orderInfo['money']){
            $result['msg'] = '订单金额与支付金额不一致';
            return $result;
        }
        $time = time();
        Db::startTrans();
        try
        {
            $up_order = [
                'pay_code'=>$invoice_no,
                'orderswitch'=>1,
            ];
            Order::where('id','=',$orderInfo['id'])->update($up_order);
            if($prodata['type_status'] == 1) {
                UserModel::where('id','=',$orderInfo['member_id'])->setInc('score',$prodata['addcoin']);
            }else if($prodata['type_status'] == 2){

                $userInfo = UserModel::field('id,viptime,discount_time')->where('id','=',$orderInfo['member_id'])->find();
                if($userInfo['viptime'] > $time){
                    $viptime = $userInfo['viptime']+$prodata['addvip']*86400;
                }else{
                    $viptime = $time+$prodata['addvip']*86400;
                    if($orderInfo['discount'] && $orderInfo['pro_id']== 3){
                        $viptime = $time+2*$prodata['addvip']*86400;
                    }
                }
                UserModel::where('id','=',$userInfo['id'])->setField('viptime',$viptime);
            }
        }
        catch(\Exception $e)
        {
            Db::rollback();
            $result['msg'] = '回调失败';
            return $result;
        }
        Db::commit();
        $result['status'] = 1;
        $result['msg'] = '支付成功';
        return $result;
    }

}