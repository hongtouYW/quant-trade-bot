<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/13
 * Time: 0:06
 */

namespace app\index\controller;

use think\cache\driver\Redis;

class Notice extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Notice();
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(in_array($param['is_show'],['0','1'],true)){
            $where[] = ['is_show','eq',$param['is_show']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('sort desc,id desc')->select();
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

    public function info(){
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
        $where[] = ['id','eq',$id];
        $res =$this->model->infoData($where);
        $this->assign('info',$res['info']);
        return $this->fetch('info');
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

    public function clearCache() {
        $redis = new Redis();
        $keys  = $redis->keys('notice*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除公告缓存成功!');
    }

}