<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:23
 */

namespace app\index\controller;

use app\index\model\Configs;
use app\index\model\Category;
use think\cache\driver\Redis;

class Tags extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Tags();
        $this->assign('img_url',Configs::get('no_au_thumb_url'));
    }


    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:25;
        $where = [];
        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        if(in_array($param['is_show'],['0','1'],true)){
            $where[] = ['is_show','eq',$param['is_show']];
        }
        if(in_array($param['is_menu'],['0','1'],true)){
            $where[] = ['is_menu','eq',$param['is_menu']];
        }
        if(in_array($param['is_top'],['0','1'],true)){
            $where[] = ['is_top','eq',$param['is_top']];
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
        $id       = input('id');
        $where    = [];
        $where[]  = ['id','eq',$id];
        $res      = $this->model->infoData($where);
        $category = Category::where('is_show', 1)->order('sort')->select();
        
        $this->assign([
            'info'     => $res['info'],
            'category' => $category
        ]);
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
        $post = request()->post();
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

        // Limit check for is_top
        if ($post['field'] === 'is_top' && intval($post['value']) === 1) {
            $count = $this->model->where('is_top', 1)->count();
            if ($count >= 20) {
                $this->error('最多只能开启20个置顶');
            }
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
    
    public function clearCache() {
        $redis = new Redis();
        $keys  = $redis->keys('video_tags*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除标签缓存成功!');
    }
}