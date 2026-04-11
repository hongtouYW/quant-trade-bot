<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/5/25
 * Time: 13:34
 */

namespace app\index\controller;


use app\index\model\Order;
use think\Controller;
use think\Db;

class Test1 extends Controller
{

    public function statistic(){

        $start=mktime(0,0,0,date('m'),date('d'),date('Y'));

        $order = Order::field('FROM_UNIXTIME(add_time,"%Y-%m-%d") as date,pid,count(id) as num,sum(money) as total')
            ->where('is_kl','=',0)->where('status','=',1)->where('add_time','<',$start)->group('date,pid')
            ->select()->append(['site'])->toArray();

        $data=array_merge($order);
        array_multisort(array_column($data,'date'),SORT_ASC,$data);

        foreach ($data as $v){

            $ins = [
                'site'=>$v['site'],
                'pay_num'=>$v['num'],
                'pay_total'=>$v['total'],
                'pid'=>$v['pid'],
                'date'=>$v['date']
            ];

            Db::name('statistic')->insert($ins);

        }

        echo 'success';

    }

}