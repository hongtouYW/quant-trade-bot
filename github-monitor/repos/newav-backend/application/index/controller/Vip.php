<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/15
 * Time: 15:18
 */

namespace app\index\controller;
use app\index\model\Vip as VipModel;
use app\index\model\SystemLog;
class Vip extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new VipModel();
    }


    public function index()
    {
        $param = input();
        $where=[];
        $order='sort desc';
        $res = $this->model->listData($where,$order);

        $this->assign('list',$res['list']);
        $this->assign('param',$param);
        return $this->fetch();
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = $this->model->saveData($param);
            if($res['code']>1){
                return $this->error($res['msg']);
            }

            $logModel = new SystemLog();
            $logParam['admin_id'] = $this->adminInfo['id'];
            $content = json_encode($param,JSON_UNESCAPED_UNICODE);
            $logParam['title'] = "操作VIP";
            $logParam['content'] = "管理员:【{$this->adminInfo['id']}】操作VIP: params: {$content}";
            $logModel->insertLog($logParam);
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res = $this->model->infoData($where);
        $this->assign('info',$res['info']);
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