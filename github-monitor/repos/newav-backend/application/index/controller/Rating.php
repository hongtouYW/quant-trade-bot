<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;
use app\index\model\Rating as RatingModel;
use think\cache\driver\Redis;
use app\index\model\Configs;
use think\Db;

class Rating extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\index\model\Rating();
    }

    public function index(){
        $param          = input();
        $param['page']  = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where          = [];
        $order          = 'id desc';

        if(in_array($param['status'],['0','1','2'],true)){
            $where[] = ['status','eq',$param['status']];
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
        $data = [];
        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }

    public function reject(){
        $id     = input("param.id");
        $result = $this->model->where("id", $id)->update(['status' => 2]);
        if ($result){
            return json(["code"=>1,"msg"=>"拒绝成功"]);
        }else{
            return json(["code"=>0,"msg"=>"拒绝失败"]);
        }
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
}