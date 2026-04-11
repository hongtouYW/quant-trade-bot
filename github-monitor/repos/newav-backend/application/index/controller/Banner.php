<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/22
 * Time: 19:01
 */

namespace app\index\controller;

use app\index\model\SystemLog;
use app\index\model\Configs;
use think\cache\driver\Redis;

class Banner extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Banner();
        $this->assign('img_url',Configs::get('banner_url'));
    }


    //主页
    public function index(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:15;
        $where = [];

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['title'] =['like','%'.$param['wd'].'%'];
        }
        if(in_array($param['is_show'],['0','1'],true)){
            $where[] = ['is_show','eq',$param['is_show']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('is_show desc,sort desc')->select();
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


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if(empty($param['sort'])){
                unset($param['sort']);
            }
            $res = $this->model->saveData($param);
            if($res['code'] != 1){
                return $this->error($res['msg']);
            }
            $logModel = new SystemLog();
            $logParam['admin_id'] = $this->adminInfo['id'];
            $content = json_encode(request()->post(),JSON_UNESCAPED_UNICODE);
            try{
                if($param['id']){
                    $logParam['title'] = "修改Banner";
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】修改Banner:{$list['id']} params: {$content}";

                }else{
                    $logParam['title'] = "添加Banner";
                    $logParam['content'] = "管理员:【{$this->adminInfo['id']}】添加Banner: params: {$content}";
                }
                $logModel->insertLog($logParam);
            }catch(\Exception $e){
                $this->error($e->getMessage());
            }
            return $this->success($res['msg']);
        }
        $id = input('id');
        $where=[];
        $where['id'] = ['eq',$id];
        $res =$this->model->infoData($where);

        // Get all package data for dropdowns
        $vipList = [];
        $pointList = [];
        $coinList = [];
        
        // Assuming you have these models
        if (class_exists('\app\index\model\Vip')) {
            $vipModel = new \app\index\model\Vip();
            $vipList = $vipModel->where('status', 1)->field('id,title')->select();
        }
        
        if (class_exists('\app\index\model\Point')) {
            $pointModel = new \app\index\model\Point();
            $pointList = $pointModel->where('status', 1)->field('id,title')->select();
        }
        
        if (class_exists('\app\index\model\Coin')) {
            $coinModel = new \app\index\model\Coin();
            $coinList = $coinModel->where('status', 1)->field('id,title')->select();
        }

        $this->assign('info', $res['info']);
        $this->assign('vipList', $vipList);
        $this->assign('pointList', $pointList);
        $this->assign('coinList', $coinList);
        $this->assign('info',$res['info']);
        return $this->fetch('info');
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

    public function video_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:15;
        $where = [];
        // $where[] = ['mosaic','=',1];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] = ['title','=',$param['wd']];
        }
        $videlModel = new \app\index\model\Video();
        $order='id desc';
        $res = $videlModel->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch();

    }


    public function actor_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['name'] =['=',$param['wd']];
        }
        $actorModel = new \app\index\model\Actor();
        $order='id asc';
        $res = $actorModel->listData($where,$order,$param['page'],$param['limit']);
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);
        return $this->fetch();

    }

    public function vip_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['name'] = ['like', '%'.$param['wd'].'%'];
        }
        
        // Assuming you have a VipPackage model
        $vipModel = new \app\index\model\Vip();
        $order = 'id asc';
        $res = $vipModel->listData($where, $order, $param['page'], $param['limit']);
        
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch('vip_list');
    }

    public function point_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['name'] = ['like', '%'.$param['wd'].'%'];
        }
        
        // Assuming you have a PointPackage model
        $pointModel = new \app\index\model\Point();
        $order = 'id asc';
        $res = $pointModel->listData($where, $order, $param['page'], $param['limit']);
        
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch('point_list');
    }

    public function coin_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;

        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where['name'] = ['like', '%'.$param['wd'].'%'];
        }
        
        // Assuming you have a CoinPackage model
        $coinModel = new \app\index\model\Coin();
        $order = 'id asc';
        $res = $coinModel->listData($where, $order, $param['page'], $param['limit']);
        
        $this->assign([
            'list'  => $res['list'],
            'total' => $res['total'],
            'page'  => $res['page'],
            'limit' => $res['limit'],
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch('coin_list');
    }

    public function clearCache() {
        $redis = new Redis();
        $keys  = $redis->keys('banner*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除Banner缓存成功!');
    }
}