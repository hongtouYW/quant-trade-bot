<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/15
 * Time: 15:18
 */

namespace app\index\controller;
use app\index\model\Domain as DomainModel;

class Domain extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new DomainModel();
    }


    public function index()
    {
        $param = input();
        $where=[];
        $order='id';
        $res = $this->model->listData($where,$order);

        $this->assign('list',$res['list']);
        $this->assign('param',$param);
        return $this->fetch();
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = $this->model->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];

        $res = $this->model->infoData($where);

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