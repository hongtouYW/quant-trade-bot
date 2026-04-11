<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/18
 * Time: 9:50
 */

namespace app\index\controller;

use app\index\model\CoinRecord;
use app\index\model\SystemLog;
use think\Db;
use think\Model;

class User extends Base
{
    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where=[];
        if(in_array($param['status'],['0','1'],true)){
            $where[] = ['status','=',$param['status']];
        }
        if(in_array($param['vip'],['1','2'],true)){
            if($param['vip'] == '2'){
                $where[] = ['viptime','>=',time()];
            }else if($param['vip'] == 1){
                $where[] = ['viptime','<',time()];
            }
        }
        if(!empty($param['id'])){
            $where[] = ['id','=',$param['id']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] = ['username', 'LIKE', '%' . $param['wd'] . '%'];
        }
        $total = model('user')->where($where)->count();
        $list =  model('user')->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
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
        $id=input("param.id");
        $find=model('user')->field('id,username')->where('id',$id)->find();
        $this->assign("find",$find);
        return $this->fetch();
    }


    /*
 * 编辑
 */
    public function edit(){
        $id=input("param.id");
        if (request()->isPost()){
            $list=request()->post();
            $list['viptime'] = !empty($list['viptime'])?strtotime($list['viptime']):0;
            $result=model('user')->where(["id"=>$list["id"]])->update($list);
            if ($result !== false){
                try{
                    $logModel = new SystemLog();
                    $logParam['title'] = "修改用户";
                    $logParam['user_id'] = $list['id'];
                    $logParam['admin_id'] = $this->adminInfo['id'];
                    $content = json_encode(request()->post(),JSON_UNESCAPED_UNICODE);
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】修改用户:{$list['id']} params: {$content}";
                    $logModel->insertLog($logParam);
                }catch (\Exception $e){
                    return json(["code"=>0,"msg"=>"编辑失败"]);
                }
                return json(["code"=>1,"msg"=>"编辑成功"]);
            }else{
                return json(["code"=>0,"msg"=>"编辑失败"]);
            }
        }
        $find=model('user')->where(["id"=>$id])->find();
        $this->assign("find",$find);
        return  $this->fetch("edit");
    }


    /*
    * 编辑
    */
    public function add_coin(){
        exit();
        $id=input("param.id");
        if (request()->isPost()){
            $list=request()->post();
            Db::startTrans();
            try {
                $up_user = model('user')->where(["id"=>$list["id"]])->setInc('coin',$list['coin']);
                if(!$up_user){
                    Db::rollback();
                    return json(["code"=>0,"msg"=>"操作失败"]);
                }
                $record = [
                    'uid'=>$list['id'],
                    'coin'=>$list['coin'],
                    'type'=>4,
                    'source_id'=>$list['id'],
                    'add_time'=>time()
                ];
                $add_coin = CoinRecord::insert($record);
                if(!$add_coin){
                    Db::rollback();
                    return json(["code"=>0,"msg"=>"操作失败"]);
                }
                $logModel = new SystemLog();
                $logParam['title'] = "修改用户";
                $logParam['user_id'] = $list['id'];
                $logParam['admin_id'] = $this->adminInfo['id'];
                $logParam['content'] = "管理员:【{$this->adminInfo['id']}】给用户:{$list['id']}增加了{$list['coin']}钻石";
                $logModel->insertLog($logParam);
                Db::commit();
                return json(["code"=>1,"msg"=>"操作成功"]);
            }catch (\Exception $e){
                Db::rollback();
                return json(["code"=>0,"msg"=>"操作失败"]);
            }
        }
        $find=model('user')->where(["id"=>$id])->find();
        $this->assign("find",$find);
        return  $this->fetch();
    }

}