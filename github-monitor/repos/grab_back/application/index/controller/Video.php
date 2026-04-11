<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;


class Video extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Video();
        $tagList = model('tags')->field('id,name')->where('is_show','=',1)->order('sort desc')->select();
        $this->assign('tagList',$tagList);
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['identifier'])){
            $param['identifier'] = trim($param['identifier']);
            $where[] =['identifier','=',$param['identifier']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['title','like','%'.$param['wd'].'%'];
        }
        if(!empty($param['mash'])){
            $param['mash'] = trim($param['mash']);
            $where[] =['mash','=',$param['mash']];
        }
        if(in_array($param['private'],['0','1','2'],true)){
            $where[] = ['private','eq',$param['private']];
        }
        if(in_array($param['recommend'],['0','1'],true)){
            $where[] = ['recommend','eq',$param['recommend']];
        }
        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','eq',$param['status']];
        }
        if(in_array($param['subtitle'],['0','1'],true)){
            $where[] = ['subtitle','eq',$param['subtitle']];
        }
        if(in_array($param['mosaic'],['0','1'],true)){
            $where[] = ['mosaic','eq',$param['mosaic']];
        }
        if(in_array($param['status1'],['0','1'],true)){
            $where[] = ['status1','eq',$param['status1']];
        }
        if(!empty($param['tag'])){
            $where['_string']  = "instr(CONCAT( ',', tags, ',' ),  ',".$param['tag'].",' )";
        }
        $order='id desc';
        if(in_array($param['sort'],['1','2'],true)){

            switch ($param['sort']) {
                case 1:
                    $order='publish_date desc';
                    break;
                case 2:
                    $order='publish_date asc';
                    break;
            }
        }

        $res = $this->model->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch("index");
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


    public function info()
    {
        if (Request()->isPost()) {
            $param=request()->post();
            $param['tags'] = !empty($param['tags'])?implode(',',$param['tags']):'';
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