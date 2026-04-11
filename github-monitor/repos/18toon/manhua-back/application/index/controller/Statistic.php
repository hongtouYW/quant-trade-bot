<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/5/25
 * Time: 14:20
 */

namespace app\index\controller;


class Statistic extends Base
{

    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Statistic();
    }
    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(in_array($param['site'],['1','2','3','4'],true)){
            $where[] = ['site','eq',$param['site']];
        }
        if(in_array($param['pid'],['1','2','3','4'],true)){
            $where[] = ['pid','eq',$param['pid']];
        }

        if(!empty($param['start'])){
            $where[] = ['date','>=',$param['start']];
        }

        if(!empty($param['end'])){
            $where[] = ['date','<=',$param['end']];
        }

        $pay_num = $this->model->where($where)->sum('pay_num');
        $pay_total = $this->model->where($where)->sum('pay_total');
        $this->assign('pay_num',$pay_num);
        $this->assign('pay_total',$pay_total);

        $total = $this->model->where($where)->count();
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

        return $this->fetch();
    }

}