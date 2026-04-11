<?php

namespace app\index\controller;
use app\index\model\Coin as CoinModel;
use app\index\model\Configs;

class Coin extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        $this->model = new CoinModel();
        $this->assign('img_url',Configs::get('no_au_thumb_url'));
    }

    public function index()
    {
        $where=[];
        $order='sort desc';
        $res = $this->model->listData($where,$order);
        $this->assign('list',$res['list']);
        return $this->fetch("index");
    }

    public function modify() {
        $this->checkPostRequest();
        $post = request()->post();
        $rule = [
            'id|ID'      => 'require',
            'field|字段' => 'require',
            'value|值'   => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        $data = [$post['field'] => $post['value']];
        $row->save($data);
        $this->success('保存成功');
    }

    public function del(){
        $id=input("param.id");
        $result=$this->model->where(["id"=>$id])->delete();
        if ($result){
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }

    public function info()
    {
        if (Request()->isPost()) {
            $param=request()->post();
            $res = $this->model->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        $id          = input('id');
        $where       = [];
        $where['id'] = $id;
        $res         = $this->model->infoData($where);
        $info        = $res['info'];
        $this->assign('info',$info);
        return $this->fetch();
    }
}