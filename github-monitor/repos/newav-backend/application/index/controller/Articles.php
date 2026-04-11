<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/20
 * Time: 14:40
 */

namespace app\index\controller;


class Articles extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Articles();

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
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['title','like','%'.$param['wd'].'%'];
        }

        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','eq',$param['status']];
        }

        $list = $this->model
            ->field('id,title,mash,actor,status,publish_date')
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

    public function info(){

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
        $where['id'] = $id;
        $res = $this->model->infoData($where);
        $info = $res['info'];
        $this->assign('info',$info);
        return $this->fetch();
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
        $data =  [];

        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }

    public function video_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:15;
        $where = [];
        $where[] = ['status','=',1];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['title','like','%'.$param['wd'].'%'];
        }
        if(!empty($param['mash'])){
            $param['mash'] = trim($param['mash']);
            $where[] =['mash','=',$param['mash']];
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
        $where = [];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
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