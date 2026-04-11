<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/5/23
 * Time: 16:57
 */

namespace app\crontab\command;


use app\index\model\Order;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Statistic extends Command
{
    protected function configure()
    {
        $this->setName('statistic')->setDescription('定时计划：统计每日订单数据');
    }
    protected function execute(Input $input, Output $output)
    {

        $date = date("Y-m-d",strtotime("-1 day"));


        $is_handle = \app\index\model\Statistic::where('date','=',$date)->value('id');

        if($is_handle){
            echo 'yesterday is handel';
            exit();
        }

        $order = Order::field('pid,count(id) as num,sum(money) as total')
            ->where('is_kl','=',0)->where('status','=',1)->whereTime('add_time','yesterday')->group('pid')
            ->select()->append(['site'])->toArray();
        $data=array_merge($order);

        foreach ($data as $v){

            $ins = [
                'site'=>$v['site'],
                'pay_num'=>$v['num'],
                'pay_total'=>$v['total'],
                'pid'=>$v['pid'],
                'date'=>$date
            ];

            Db::name('statistic')->insert($ins);

        }

        echo 'handle success';
        exit();

    }

}