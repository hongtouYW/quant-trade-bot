<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/14
 * Time: 22:30
 */

namespace app\index\controller;

use app\extra\PHPGangsta\GoogleAuthenticator;
use app\index\model\SystemLog;

class Admin extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Admin();

    }
    public function index(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        $where=[];

        $status =input("param.status","");
        $wd=input("param.wd","");

        if(in_array($status,['1','2'],true)){
            $where[] = ['status','eq',$status];
        }
        if(!empty($wd)){
            $wd = trim($wd);
            $where[] = ['username','like', '%'.$wd.'%'];
        }
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
        return $this->fetch();
    }


    /*
     * 添加管理员
     */
    public function add(){
        if (request()->isPost()){
            $list=request()->post();
            $list["ori_password"]=input("param.password");
            $list["password"]=pswCrypt($list["ori_password"]);
            $list["add_time"]=time();
            $find=$this->model->where(["username"=>$list["username"]])->find();
            if ($find){
                return json(["code"=>0,"msg"=>"已存在该管理员"]);
            }
            $result=$this->model->insertGetId($list);
            if ($result){
                try {
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改管理员";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】添加的新的管理员:$result";
                    $logModel->insertLog($logParam);
                }catch (\Exception $e){

                }
                return json(["code"=>1,"msg"=>"添加成功"]);
            }else{
                return json(["code"=>0,"msg"=>"添加失败"]);
            }

        }
        $role=model('auth_role')->select();
        $this->assign("role",$role);
        return $this->fetch("add");
    }


    /*
     * 编辑
     */
    public function edit(){
        $id=input("param.id");
        if (request()->isPost()){
            $list=request()->post();
            $find=$this->model->where('username','=',$list['username'])->where('id','<>',$list['id'])->find();
            if ($find){
                return json(["code"=>0,"msg"=>"已存在该管理员"]);
            }

            if($list['id'] == 1 && $list['status'] == 2){
                return json(["code"=>0,"msg"=>"id为1的管理员不能禁止"]);
            }

            $list["ori_password"]=input("param.password");
            $list["password"]=pswCrypt($list["ori_password"]);
            $result=$this->model->where(["id"=>$list["id"]])->update($list);
            if ($result !== false){
                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改管理员";
                    $logParam['user_id'] = 0;
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】修改管理员:{$list['id']}";
                    $logModel->insertLog($logParam);
                }catch (DbException $e){
                }
                return json(["code"=>1,"msg"=>"编辑成功"]);
            }else{
                return json(["code"=>0,"msg"=>"编辑失败"]);
            }
        }
        $find=$this->model->where(["id"=>$id])->find();
        $this->assign("find",$find);
        $role=model("authrole")->select();
        $this->assign("role",$role);
        return  $this->fetch("edit");
    }



    /*
     * 删除
     */
    public function del(){
        $id=input("param.id");
        if($id == 1){
            return json(["code"=>0,"msg"=>"id为1的管理员不能删除"]);
        }
        $result=$this->model->where(["id"=>$id])->delete();
        if ($result){
            try {
                $logModel = new SystemLog();
                $logParam['title'] = "删除管理员";
                $logParam['user_id'] = 0;
                $logParam['admin_id'] = $this->adminInfo['id'];
                $logParam['content'] = "管理员:【{$this->adminInfo['id']}】删除了管理员:$id}";
                $logModel->insertLog($logParam);
            }catch (\Exception $e){

            }
            return json(["code"=>1,"msg"=>"删除成功"]);
        }else{
            return json(["code"=>0,"msg"=>"删除失败"]);
        }
    }


    /*
     * 管理员状态
     */
    public function status()
    {
        $param=input("param.");

        if($param['id'] == 1){
            return json(["code"=>0,"msg"=>"id为1的管理员不能操作"]);
        }
        if($param['val'] == 1){
            $status = 2;
            $text = '禁止';
        }else{
            $status = 1;
            $text = '开启';
        }
        $result = $this->model->where('id','=',$param['id'])->setField('status',$status);
        if($result !== false){
            try {
                $logModel = new SystemLog();
                $logParam['title'] = "修改管理员状态";
                $logParam['user_id'] = 0;
                $logParam['admin_id'] = $this->adminInfo['id'];
                $logParam['content'] = "管理员:【{$this->adminInfo['id']}】{$text}了管理员:{$param['id']}";
                $logModel->insertLog($logParam);
            }catch (\Exception $e){

            }
            return json(["code"=>1,"msg"=>"操作成功"]);
        }
        return json(["code"=>0,"msg"=>"操作失败"]);
    }

    /*
     * 重置谷歌秘钥
     */
    public function upgoogleSecret(){
        $id = input('id','');
        $ga = new GoogleAuthenticator();
        $secret = $ga->createSecret();
        $result = $this->model->where('id','=',$id)->setField('google_secret', $secret);
        if ($result){
            try{
                $logModel = new SystemLog();
                $logParam['title'] = "重置谷歌密钥";
                $logParam['user_id'] = 0;
                $logParam['admin_id'] = $this->adminInfo['id'];
                $logParam['content'] = "管理员:【{$this->adminInfo['id']}】重置了管理员:{$id}的谷歌密钥";
                $logModel->insertLog($logParam);
            }catch (DbException $e){

            }
            return json(["code"=>1,"msg"=>"重置成功"]);
        }else{
            return json(["code"=>0,"msg"=>"重置失败"]);
        }
    }
}