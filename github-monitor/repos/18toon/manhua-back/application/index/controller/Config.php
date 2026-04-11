<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/31
 * Time: 12:55
 */

namespace app\index\controller;

use app\index\model\Configs as ConfigsModel;
use think\cache\driver\Redis;

class Config extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new ConfigsModel();
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:20;
        $where=[];
        $admin_name=session('admin_name');
        if($admin_name != 'bestadmin'){
            $where[] = ['id','<>','13'];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('id asc')->select();
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


    /*
     * 删除
     */
/*    public function del(){
        $id=input("param.id");
        $result=$this->model->where(["id"=>$id])->delete();
        if ($result){
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }*/


    public function clear() {
        $redis = new Redis();
        $keys = $redis->keys('config*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除Config缓存成功!');
    }




}