<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/16
 * Time: 20:00
 */

namespace app\index\controller;

use app\index\model\SystemLog;

class Authrole extends Base
{


    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Authrole();

    }

    //主页
    public function index(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        $this->assign("is_role",is_role());
        return $this->fetch("index");
    }





    //    角色的添加
    public function add(){
        if (request()->isPost()){
            $list=request()->post();
            $findname=$this->model->where("role_name","=",$list["role_name"])->find();
            if (!empty($findname)){
                return json(["code"=>0,"msg"=>"该角色名称已存在"]);
            }
            $list["add_time"]=date("Y-m-d H:i:s");
            $result=$this->model->insertGetId($list);
            if ($result){
/*                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "添加角色";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】添加了新的角色:{$result}";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){

                }*/
                return json(["code"=>1,"msg"=>"添加成功"]);
            }else{
                return json(["code"=>0,"msg"=>"添加失败"]);
            }
        }
        return $this->fetch("add");
    }

    /*
     * 编辑
     */
    public function edit(){
        $role_id=input("param.role_id");
        if (request()->isPost()){
            $list=request()->post();
            $result=$this->model->where("role_id","=",$list["role_id"])->update($list);
            if ($result !== false){
/*                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改角色";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】修改了角色:{$list['role_id']}";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){

                }*/
                return json(["code"=>1,"msg"=>"编辑成功"]);
            }else{
                return json(["code"=>0,"msg"=>"编辑失败"]);
            }
        }
        $where["role_id"]=["=",$role_id];
        $find=$this->model->where($where)->find();
        $this->assign("find",$find);
        return $this->fetch("edit");
    }

    /*
     * 删除
     */
    public function del(){
        $role_id=input("param.role_id");
        $result=$this->model->where(["role_id"=>$role_id])->delete();
        if ($result){
/*            try{
                $logModel = new SystemLog();
                $logParam['title'] = "修改角色";
                $logParam['user_id'] = 0;
                $logParam['admin_id'] = $this->adminInfo['id'];
                $logParam['content'] = "管理员:【{$this->adminInfo['id']}】删除了角色:{$role_id}";
                $logModel->insertLog($logParam);
            }catch (DbException $e){

            }*/
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }


    /**
     * [分配权限]
     */
    public function give(){

        $param = input('param.');

        //获取现在的权限
        if('get' == $param['type']){
            $nodeStr = $this->model->getNodeInfo($param['id']);
            return json(["code"=>1,"data"=>$nodeStr]);
        }
        //分配新权限
        if('give' == $param['type']){

            $doparam["menu_id"]=$param['rule'];
            $result = $this->model->where(["role_id"=>$param["id"]])->update($doparam);

            if ($result !== false){
/*                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改角色";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】为角色{$param["id"]}重新分配了权限";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){

                }*/
                return json(["code"=>1,"msg"=>"分配权限成功"]);
            }else{
                return json(["code"=>0,"msg"=>"分配权限失败"]);
            }
        }
    }
}