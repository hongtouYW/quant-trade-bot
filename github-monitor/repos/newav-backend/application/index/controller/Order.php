<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 16:12
 */

namespace app\index\controller;

use app\index\model\Platforms as PlatformsModel;
use app\index\model\Vip as VipModel;
class Order extends Base
{

    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Order();
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:20;
        $where=[];
        // $where[] = ['is_kl','=',0];
        if(!empty($param['uid'])){
            $param['uid'] = trim($param['uid']);
            $where[] =['uid','=',$param['uid']];
        }

        if(in_array($param['product_type'],['1','2','3'],true)){
            $where[] = ['product_type','eq',$param['product_type']];
        }

        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','eq',$param['status']];
        }
        if(!empty($param['timegap'])){
            $gap = explode('至', $param['timegap']);
            $begin_time = strtotime($gap[0]);
            $end_time = strtotime($gap[1]);
            $where[] = ['add_time','between time',[$begin_time,$end_time]];
            if ($end_time - $begin_time <=  3600) {
                array_splice($where,0,1);
            }
        }
        if(!empty($param['order_sn'])){
            $param['order_sn'] = trim($param['order_sn']);
            $where[]  = ['order_sn','=',$param['order_sn']];
        }
        if(!empty($param['pay_code'])){
            $param['pay_code'] = trim($param['pay_code']);
            $where[]  = ['pay_code','=',$param['pay_code']];
        }
        // $where2 = [
        //     'bounded'=>1,
        // ];

//         if(count($param) > 2){
//             unset($where2['bounded']);
// /*            if(in_array($param['status'],['0','1'],true) && !empty($param['timegap'])){

//             }else{
//                 array_splice($where,0,1);
//             }*/
//         }


        // if(!empty($param['uid']) || !empty($param['order_sn']) || !empty($param['pay_code'])){
        //     array_splice($where,0,1);
        // }

        $total = $this->model->where($where)->count();
        // $total = $this->model->where($where)->where('is_kl','=',0)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();

        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

/*        $platList = PlatformsModel::field('id,remark')->select();
        $this->assign('platList',$platList);

        $vipList = VipModel::field('id,title')->select();
        $this->assign('vipList',$vipList);*/
        return $this->fetch();
    }

}