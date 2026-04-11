<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/15
 * Time: 23:29
 */

namespace app\index\controller;

use app\index\model\SystemLog;

class Authmenu extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Authmenu();

    }

    public function index(){
        if (request()->isAjax()){
            $data=$this->model->field("id,parent_id as pid,name as title,status,url,type,ico,sort")->order("sort desc,id asc ")->select();
            $result["data"]=$data;
            $result["code"]=1;
            return json($result);
        }
        return $this->fetch("index");
    }


    /*
     * 添加
     */
    public function add(){
        if (request()->isPost()){
            $list=request()->post();
            if(empty($list['sort'])){
                unset($list['sort']);
            }
            if (empty($list["parent_id"])){
                $list["type"]="menu";  //菜单栏控制
                $list["ico"]= input("ico");
                $list["parent_id"]=0;
                $list["url"]="#";
            }elseif ($list["parent_id"]!=0){
                $find=$this->model->where("id","=",$list["parent_id"])->find();
                if ($find["parent_id"]==0){
                    $list["type"]="menu";  //菜单栏控制
                }else{
                    $list["type"]="per";  //节点栏控制
                }
            }
            $findname=$this->model->where("name","=",$list["name"])->find();
            if (!empty($findname)){
                return json(["code"=>0,"msg"=>"该菜单名称已存在"]);
            }
            $result=$this->model->insertGetId($list);
            if ($result){
/*                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "添加菜单";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】添加的新的菜单：$result";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){

                }*/
                return json(["code"=>1,"msg"=>"添加成功"]);
            }else{
                return json(["code"=>0,"msg"=>"添加失败"]);
            }
        }
        $id=input("param.id");
        $find=$this->model->where(["id"=>$id])->find();
        $this->assign("find",$find);
        return $this->fetch("add");
    }


    /*
     * 编辑
     */
    public function edit(){

        $id=input("param.id");
        if (request()->isPost()){
            $list=request()->post();
            if(empty($list['sort'])){
                unset($list['sort']);
            }
            $find=$this->model->where('name','=',$list['name'])->where('id','<>',$list['id'])->find();
            if ($find){
                return json(["code"=>0,"msg"=>"该菜单名称已存在"]);
            }
            $result=$this->model->where("id","=",$list["id"])->update($list);
            if ($result !== false){
/*                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改菜单";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】修改了菜单：{$list["id"]}";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){

                }*/
                return json(["code"=>1,"msg"=>"编辑成功"]);
            }else{
                return json(["code"=>0,"msg"=>"编辑失败"]);
            }
        }
        $where["id"]=["=",$id];
        $find=$this->model->where($where)->find();
        $this->assign("find",$find);
        return $this->fetch("edit");
    }


    /*
     * 删除
     */
    public function del(){
        $id=input("param.id");
        $data=$this->model->del_menu($id);
        try{
            $logModel = new SystemLog();
            $logParam['title'] = "删除菜单";
            $logParam['user_id'] = 0;
            $logParam['admin_id'] = $this->adminInfo['id'];
            $logParam['content'] = "管理员:【{$this->adminInfo['id']}】删除了菜单：$id";
            $logModel->insertLog($logParam);
        }catch (DbException $e){

        }
        return json($data);
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

}