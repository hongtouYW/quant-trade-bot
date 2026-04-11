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
            $order_no = $result["orderId"]; //系统订单号
            $invoice_no = $result['sourceId']; //盈利订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];

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


    public function callbackwxrwuma(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackwxrwuma");

            //初始化
            $order_no = $result["orderId"]; //系统订单号
            $invoice_no = $result['sourceId']; //盈利订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];

            if ($status == '1') {

                $res = $this->doWumaCallback($amount,$order_no, $invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackwumawxr_success');
                } else {
                    save_log($res,'callbackwummwxr_error');
                }

            }
            //支付成功或失败 都返回OK
            echo "OK";
            exit;
        }

    }

    public function callbackwxrdm(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackwxrdm");

            //初始化
            $order_no = $result["orderId"]; //系统订单号
            $invoice_no = $result['sourceId']; //盈利订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];

            if ($status == '1') {

                $res = $this->doDmCallback($amount,$order_no, $invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackwxrdm_success');
                } else {
                    save_log($res,'callbackwxrdm_error');
                }

            }
            //支付成功或失败 都返回OK
            echo "OK";
            exit;
        }

    }

    public function callbackwxr4k(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackwxr4k");

            //初始化
            $order_no = $result["orderId"]; //系统订单号
            $invoice_no = $result['sourceId']; //盈利订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];

            if ($status == '1') {

                $res = $this->do4kCallback($amount,$order_no, $invoice_no);
                if ($res['status'] == 1) {
                    save_log($res,'callbackwxr4k_success');
                } else {
                    save_log($res,'callbackwxr4k_error');
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

    public function successwxrwuma()
    {
        //记录LOG
        //转跳至前端
        $jsondata = input('get.');
        save_log($jsondata, "successwxrwuma");

        echo "success";
        //$return_url = ""; //需要前端给我 再hardcode进来
        //header('location: '.$return_url);
    }

    public function successwxrdm()
    {
        //记录LOG
        //转跳至前端
        $jsondata = input('get.');
        save_log($jsondata, "successwxrdm");

        echo "success";
        //$return_url = ""; //需要前端给我 再hardcode进来
        //header('location: '.$return_url);
    }

    public function successwxr4k()
    {
        //记录LOG
        //转跳至前端
        $jsondata = input('get.');
        save_log($jsondata, "successwxr4k");

        echo "success";
        //$return_url = ""; //需要前端给我 再hardcode进来
        //header('location: '.$return_url);
    }


    public function callbackgz(){

        if (Request()->isPost()) {

            //拿取POST参数
            $result = input('post.');
            save_log($result,"callbackgz");

            //初始化
            $order_no = $result["order_no"]; //系统订单号
            $invoice_no = $result["invoice_no"]; //唐朝订单号
            $status = $result["success"]; //支付状态 1： 已支付，2：支付失败
            $amount = $result["amount"];
            $custom_column = isset($result['custom_column'])?$result['custom_column']:'';
            if ($status == '1') {
                switch ($custom_column) {
                    case '4k':
                        $res = $this->do4kCallback($amount,$order_no, $invoice_no);
                        break;
                    case 'dm':
                        $res = $this->doDmCallback($amount,$order_no, $invoice_no);
                        break;
                    case 'wuma':
                        $res = $this->doWumaCallback($amount,$order_no, $invoice_no);
                        break;
                    default:
                        $res = $this->doCallback($amount,$order_no, $invoice_no);
                        break;
                }
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


    //有码站逻辑处理
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
            'pay_code'=>$invoice_no,
            'status'=>1,
            'pay_time'=>$time
        ];
        Db::startTrans();
        $is_up = VipOrder::where('id','=',$orderInfo['id'])->update($order);
        if(!$is_up){
            Db::rollback();
            $result['msg'] = '修改订单状态失败';
            return $result;
        }

        $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time,p')->where('id','=',$orderInfo['uid'])->find();

        if(!$userInfo){
            Db::rollback();
            $result['msg'] = '回调用户不存在';
            return $result;
        }

        if ($userInfo['vip_end_time'] > $time) {
            $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
        }else{
            $user['vip_begin_time'] = $time;
            $user['vip_end_time'] = $time+$orderInfo['day']*86400;
        }

        if($orderInfo['vid'] == 5){
            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 6){
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 7){
            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 8){

            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['k4_end_time'] > $time) {
                $user['k4_end_time'] = $userInfo['k4_end_time']+$orderInfo['day']*86400;
            }else{
                $user['k4_begin_time'] = $time;
                $user['k4_end_time'] = $time+$orderInfo['day']*86400;
            }
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
        $result['msg'] = '支付成功';
        return $result;
    }

    //无码站逻辑处理
    private function doWumaCallback($amount, $order_no, $invoice_no){
        $time = time();
        $result = ['status'=>0,'msg'=>'网络繁忙，请稍后再试','order_no'=>$order_no];
        $orderInfo = WumaVipOrder::where('order_sn','=',$order_no)->find();
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
            'pay_code'=>$invoice_no,
            'status'=>1,
            'pay_time'=>$time
        ];
        Db::startTrans();
        $is_up = WumaVipOrder::where('id','=',$orderInfo['id'])->update($order);
        if(!$is_up){
            Db::rollback();
            $result['msg'] = '修改订单状态失败';
            return $result;
        }

        $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time,p')->where('id','=',$orderInfo['uid'])->find();

        if(!$userInfo){
            Db::rollback();
            $result['msg'] = '回调用户不存在';
            return $result;
        }

        if ($userInfo['wm_end_time'] > $time) {
            $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
        }else{
            $user['wm_begin_time'] = $time;
            $user['wm_end_time'] = $time+$orderInfo['day']*86400;
        }


        if($orderInfo['vid'] == 5){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 6){

            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 7){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 8){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['k4_end_time'] > $time) {
                $user['k4_end_time'] = $userInfo['k4_end_time']+$orderInfo['day']*86400;
            }else{
                $user['k4_begin_time'] = $time;
                $user['k4_end_time'] = $time+$orderInfo['day']*86400;
            }
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
        $result['msg'] = '支付成功';
        return $result;
    }

    //动漫站逻辑处理
    private function doDmCallback($amount, $order_no, $invoice_no){
        $time = time();
        $result = ['status'=>0,'msg'=>'网络繁忙，请稍后再试','order_no'=>$order_no];
        $orderInfo = VipOrderDm::where('order_sn','=',$order_no)->find();
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
            'pay_code'=>$invoice_no,
            'status'=>1,
            'pay_time'=>$time
        ];
        Db::startTrans();
        $is_up = VipOrderDm::where('id','=',$orderInfo['id'])->update($order);
        if(!$is_up){
            Db::rollback();
            $result['msg'] = '修改订单状态失败';
            return $result;
        }

        $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time,p')->where('id','=',$orderInfo['uid'])->find();

        if(!$userInfo){
            Db::rollback();
            $result['msg'] = '回调用户不存在';
            return $result;
        }

        if ($userInfo['dm_end_time'] > $time) {
            $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
        }else{
            $user['dm_begin_time'] = $time;
            $user['dm_end_time'] = $time+$orderInfo['day']*86400;
        }

        if($orderInfo['vid'] == 5){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 6){
            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 7){

            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }

            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
        }elseif ($orderInfo['vid'] == 8){

            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }

            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['k4_end_time'] > $time) {
                $user['k4_end_time'] = $userInfo['k4_end_time']+$orderInfo['day']*86400;
            }else{
                $user['k4_begin_time'] = $time;
                $user['k4_end_time'] = $time+$orderInfo['day']*86400;
            }
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
        $result['msg'] = '支付成功';
        return $result;
    }

    //4k站逻辑处理
    private function do4kCallback($amount, $order_no, $invoice_no){
        $time = time();
        $result = ['status'=>0,'msg'=>'网络繁忙，请稍后再试','order_no'=>$order_no];
        $orderInfo = VipOrder4k::where('order_sn','=',$order_no)->find();
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
            'pay_code'=>$invoice_no,
            'status'=>1,
            'pay_time'=>$time
        ];
        Db::startTrans();
        $is_up = VipOrder4k::where('id','=',$orderInfo['id'])->update($order);
        if(!$is_up){
            Db::rollback();
            $result['msg'] = '修改订单状态失败';
            return $result;
        }

        $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time,p')->where('id','=',$orderInfo['uid'])->find();

        if(!$userInfo){
            Db::rollback();
            $result['msg'] = '回调用户不存在';
            return $result;
        }

        if ($userInfo['k4_end_time'] > $time) {
            $user['k4_end_time'] = $userInfo['k4_end_time']+$orderInfo['day']*86400;
        }else{
            $user['k4_begin_time'] = $time;
            $user['k4_end_time'] = $time+$orderInfo['day']*86400;
        }
        if($orderInfo['vid'] == 5){
            if ($userInfo['vip_end_time'] > $time) {
                $user['vip_end_time'] = $userInfo['vip_end_time']+$orderInfo['day']*86400;
            }else{
                $user['vip_begin_time'] = $time;
                $user['vip_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['wm_end_time'] > $time) {
                $user['wm_end_time'] = $userInfo['wm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['wm_begin_time'] = $time;
                $user['wm_end_time'] = $time+$orderInfo['day']*86400;
            }
            if ($userInfo['dm_end_time'] > $time) {
                $user['dm_end_time'] = $userInfo['dm_end_time']+$orderInfo['day']*86400;
            }else{
                $user['dm_begin_time'] = $time;
                $user['dm_end_time'] = $time+$orderInfo['day']*86400;
            }
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
        $result['msg'] = '支付成功';
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