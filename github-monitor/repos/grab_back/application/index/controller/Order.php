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
        $where[] = ['is_kl','=',0];
        if(!empty($param['uid'])){
            $param['uid'] = trim($param['uid']);
            $where[] =['uid','=',$param['uid']];
        }

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[]  = ['username','=',$param['wd']];
        }
        if(!empty($param['pid'])){
            $where[]  = ['pid','eq',$param['pid']];
        }
        if(!empty($param['vid'])){
            $where[]  = ['vid','eq',$param['vid']];
        }
        if(in_array($param['pay_type'],['1','2'],true)){
            $where[] = ['pay_type','eq',$param['pay_type']];
        }

        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['a.status','eq',$param['status']];
        }
        if(!empty($param['timegap'])){
            $gap = explode('至', $param['timegap']);
            $begin_time = strtotime($gap[0]);
            $end_time = strtotime($gap[1]);
            $where[] = ['add_time','between time',[$begin_time,$end_time]];
        }
        if(!empty($param['order_sn'])){
            $param['order_sn'] = trim($param['order_sn']);
            $where[]  = ['a.order_sn','=',$param['order_sn']];
        }
        if(!empty($param['pay_code'])){
            $param['pay_code'] = trim($param['pay_code']);
            $where[]  = ['a.pay_code','=',$param['pay_code']];
        }

        $total = $this->model->alias('a')->join('user b','b.id = a.uid','left')->join('platforms c','c.id = a.pid','left')->where($where)->count();
        $list =  $this->model->alias('a')->field('a.*,b.username,c.remark')->join('user b','b.id = a.uid','left')->join('platforms c','c.id = a.pid','left')->where($where)->page($param['page'],$param['limit'])->order('a.id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $platList = PlatformsModel::field('id,remark')->select();
        $this->assign('platList',$platList);

        $vipList = VipModel::field('id,title')->select();
        $this->assign('vipList',$vipList);
        return $this->fetch();
    }

}