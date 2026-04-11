<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;
use app\index\model\Hotlist as HotlistModel;
use think\cache\driver\Redis;
use app\index\model\Configs;
use think\Db;

class Hotlist extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\index\model\Hotlist();
        $this->assign('img_url',Configs::get('no_au_thumb_url'));
    }

    public function index(){
        $param          = input();
        $param['page']  = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where          = [];

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['title','like','%'.$param['wd'].'%'];
        }
        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }
        if(in_array($param['is_show'],['0','1'],true)){
            $where[] = ['is_show','eq',$param['is_show']];
        }
        
        $order='id desc';
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
            if(empty($param['sort'])){
                unset($param['sort']);
            }
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

    public function clearCache() {
        $redis = new Redis();
        $keys  = $redis->keys('hotlist*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除热门缓存成功!');
    }
}