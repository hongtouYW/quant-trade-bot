<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/22
 * Time: 14:12
 */

namespace app\index\controller;


use app\index\model\VipOrder;
use think\Controller;
use app\index\model\User as UserModel;

class Report extends Controller
{

    public function record(){

        $key = $this->request->param('key');
        $data['code'] = 0;
        if (!isset($key) || $key != config('site.api_key')){
            $data['msg'] = 'Api密钥为空/密钥错误';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        $userModel = new UserModel();
        $orderModel = new VipOrder();
        $yes_profit = $orderModel::whereTime('pay_time','yesterday')->where('is_kl','=',0)->where('status','=',1)->sum('money');
        $yes_user = $userModel::whereTime('reg_time','yesterday')->count();

        $before_yes_start = mktime(0,0,0,date('m'),date('d')-2,date('Y'));
        $before_yes_end = mktime(0,0,0,date('m'),date('d')-1,date('Y'))-1;

        $before_yes_profit = $orderModel::whereTime('pay_time', [$before_yes_start, $before_yes_end])->where('is_kl','=',0)->where('status','=',1)->sum('money');
        $before_yes_user = $userModel::whereTime('reg_time', [$before_yes_start, $before_yes_end] )->count();
        $record = [
            'yes_profit'=>$yes_profit,
            'yes_user'=>$yes_user,
            'before_yes_profit'=>$before_yes_profit,
            'before_yes_user'=>$before_yes_user,
        ];
        $data=['code'=>1,'msg'=>'获取成功','data'=>$record];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));

    }


}