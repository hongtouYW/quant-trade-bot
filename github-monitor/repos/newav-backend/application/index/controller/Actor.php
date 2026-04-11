<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:23
 */

namespace app\index\controller;



use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Db;

class Actor extends Base
{
    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Actor();
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

        if(in_array($param['tid'],['0','1'],true)){
            if($param['tid'] == 0){
                $where[] = ['tid','=',0];
            }else{
                $where[] = ['tid','>',0];
            }
        }

        $order='id asc';
        if(in_array($param['sort'],['1','2'],true)){

            switch ($param['sort']) {
                case 1:
                    $order='sort asc';
                    break;
                case 2:
                    $order='sort desc';
                    break;
            }
        }



        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order($order)->select();
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
            
            // 验证规则
            $rule = [
                'name|姓名'      => 'require',
                'birthday|生日'  => 'date|dateFormat:Y-m-d', // 验证日期格式
                'debut|出道时间' => 'date|dateFormat:Y-m-d', // 验证日期格式
            ];

            $this->validate($param, $rule);
            
            if(empty($param['sort'])){
                unset($param['sort']);
            }
            
            // 处理生日格式
            if (empty($param['birthday'])) {
                $param['birthday'] = null;
            }
            if (empty($param['debut'])) {
                $param['debut'] = null;
            }
            
            $res = $this->model->saveData($param);
            if($res['code'] != 1){
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        
        $id      = input('id');
        $where   = [];
        $where[] = ['id','eq',$id];
        $res     = $this->model->infoData($where);
            
        $bloodTypes     = Db::name('blood_type')->where('status', 1)->order('sort')->select();
        $constellations = Db::name('constellation')->where('status', 1)->order('sort')->select();
        $nationalities  = Db::name('nationality')->where('status', 1)->order('sort')->select();
        
        $this->assign([
            'info'           => $res['info'],
            'bloodTypes'     => $bloodTypes,
            'constellations' => $constellations,
            'nationalities'  => $nationalities
        ]);
        // $this->assign('info',$res['info']);
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

    public function trend_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        $trendModel = new \app\index\model\Trend();
        $order='id asc';
        $res = $trendModel->listData($where,$order,$param['page'],$param['limit']);
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



    public function blood_type_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        $bloodTypeModel = new \app\index\model\Blood();
        $order='id asc';
        $res = $bloodTypeModel->listData($where,$order,$param['page'],$param['limit']);
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

    public function constellation_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        $constellationModel = new \app\index\model\Constellation();
        $order='id asc';
        $res = $constellationModel->listData($where,$order,$param['page'],$param['limit']);
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

    public function nationality_list(){
        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        $nationalityModel = new \app\index\model\Nationality();
        $order='id asc';
        $res = $nationalityModel->listData($where,$order,$param['page'],$param['limit']);
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


    public function actor_select_list()
    {
        return $this->fetch();
    }

    public function actor_list()
    {
        $page = input('page', 1);
        $limit = input('limit', 10);
        
        $where = [];
        $param = input();
        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] =['name','like','%'.$param['wd'].'%'];
        }
        // 获取演员列表数据
        $list = $this->model->where($where)->page($page)->limit($limit)->select();
        $count = $this->model->where($where)->count();
        
        return json([
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $list
        ]);
    }

    public function clearCache() {
        $redis = new Redis();
        $keys  = $redis->keys('actor*');
        if($keys){
            $redis->del($keys);
        }
        $this->success('清除演员缓存成功!');
    }

    // ========================
    // ==========写真==========
    // ========================

    public function photos()
    {
        $actor_id = input('actor_id');
        if (!$actor_id) {
            $this->error('缺少 actor_id');
        }

        $actor = $this->model->where('id', $actor_id)->find();
        if (!$actor) {
            $this->error('演员不存在');
        }

        $this->assign('actor_id', $actor_id);
        $this->assign('actor', $actor);

        return $this->fetch('photos');
    }

    public function photo_list()
    {
        $actor_id = input('actor_id');
        if (!$actor_id) {
            return json(['code'=>0,'msg'=>'缺少 actor_id']);
        }

        $model = new \app\index\model\ActorPhoto();
        $list  = $model->listData($actor_id);

        return json(['code'=>1,'msg'=>'获取成功','list'=>$list]);
    }

    public function photo_add()
    {
        $data = input('post.');

        if (empty($data['actor_id']) || empty($data['image'])) {
            return json(['code'=>0,'msg'=>'参数不完整']);
        }

        $model  = new \app\index\model\ActorPhoto();
        $insert = [
            'actor_id'   => intval($data['actor_id']),
            'image'      => trim($data['image']),
            'sort'       => intval($data['sort'] ?? 0),
            'created_at' => time()
        ];
        $res = $model->saveData($insert);
        return json($res);
    }

    public function photo_edit()
    {
        $data = input('post.');
        if (empty($data['id'])) {
            return json(['code'=>0,'msg'=>'缺少 id']);
        }

        $model = new \app\index\model\ActorPhoto();
        $update = [
            'sort' => intval($data['sort'] ?? 0),
            'image' => trim($data['image'] ?? '')
        ];

        $res = $model->saveData($update + ['id' => $data['id']]);
        return json($res);
    }

    public function photo_del()
    {
        $id = input('id');
        if (!$id) {
            return json(['code'=>0,'msg'=>'缺少 id']);
        }

        $model = new \app\index\model\ActorPhoto();
        $res = $model->where('id', $id)->delete();

        if ($res) {
            return json(['code'=>1,'msg'=>'删除成功']);
        }
        return json(['code'=>0,'msg'=>'删除失败']);
    }

}