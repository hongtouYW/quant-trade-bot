<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/20
 * Time: 14:40
 */

namespace app\index\controller;


class Trend extends Base
{
    protected $model= '';
    protected $detailModel= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Trend();
        $this->detailModel =new \app\index\model\TrendDetail();

    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];

        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }

        $list = $this->model
            ->field('id,name')
            ->page($param['page'],$param['limit'])
            ->where($where)
            ->order('id desc')
            ->select();

        $total = $this->model->where($where)->count();
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

    public function detail(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        $tid = input('tid');
        $where[] = ['tid','=',$tid];
        $list = $this->detailModel
            ->field('id,text,media,create_at')
            ->page($param['page'],$param['limit'])
            ->where($where)
            ->order('create_at desc')
            ->select();

        $total = $this->detailModel->where($where)->count();
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

    public function detail_info(){
        if (Request()->isPost()) {
            $param = input('post.');
            if(empty($param['sort'])){
                unset($param['sort']);
            }
            $res = $this->detailModel->saveData($param);
            if($res['code'] != 1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        $id = input('id');
        $where=[];
        $where[] = ['id','eq',$id];
        $res =$this->detailModel->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch();
    }




    /*
     * 删除
     */
    public function del(){
        $id=input("param.id");
        $result=$this->model->where(["id"=>$id])->delete();
        if ($result){
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }
}