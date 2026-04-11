<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/22
 * Time: 19:01
 */

namespace app\index\controller;


class Banner extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Banner();
    }


    //主页
    public function index(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['title'] =['like','%'.$param['wd'].'%'];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('sort desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch("index");
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(empty($param['sort'])){
                unset($param['sort']);
            }
            $res = $this->model->saveData($param);
            if($res['code'] != 1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res =$this->model->infoData($where);

        $this->assign('info',$res['info']);
        return $this->fetch('info');
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

    public function modify()
    {
        $this->checkPostRequest();
        $post=request()->post();
        $rule = [
            'id|ID'    => 'require',
            'field|字段' => 'require',
            'value|值'  => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        try {
            $row->save([
                $post['field'] => $post['value'],
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }

    public function video_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['title'] =['=',$param['wd']];
        }
        $videlModel = new \app\index\model\Video();
        $order='id desc';
        $res = $videlModel->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch();

    }


    public function actor_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['name'] =['=',$param['wd']];
        }
        $actorModel = new \app\index\model\Actor();
        $order='id asc';
        $res = $actorModel->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch();

    }
}