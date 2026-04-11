<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/6/19
 * Time: 17:03
 */

namespace app\index\controller;


use app\index\model\Configs;
use think\Db;

class Mmadv extends Base
{

    protected $model= '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model =new \app\index\model\Mmadv();
    }

    public function index(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:20;
        $where = [];
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);

        $this->assign('video_host','https://cdn.10ol0o10.com');
        return $this->fetch();

    }


    public function add(){

        $param = input();
        $param['page'] = !empty($param['page'])?$param['page']:1;
        $param['limit'] = !empty($param['limit'])?$param['limit']:10;
        $where = [];
        $where[] = ['mosaic','=',1];

        $ids = $this->model->column('vid');

        $where[] = ['id','not in',$ids];

        if(!empty($param['id'])){
            $param['id'] = trim($param['id']);
            $where[] =['id','=',$param['id']];
        }
        if(!empty($param['wd'])){
            $param['wd'] = trim($param['wd']);
            $where[] = ['title','=',$param['wd']];
        }
        if(in_array($param['private'],['0','1','2'],true)){
            $where[] = ['private','eq',$param['private']];
        }
        $total = Db::name('video')->where($where)->count();
        $list =  Db::name('video')->where($where)->page($param['page'],$param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param',$param);


        $thumb_url = Configs::get('thumb_url');
        $this->assign('thumb_url',$thumb_url);
        return $this->fetch();

    }


    public function add_adv(){

        if (Request()->isPost()) {
            $list = input('post.');
            $res = $this->model->where('vid', '=', $list['vid'])->value('id');

            if ($res) {
                return json(["code" => 0, "msg" => "该视频已添加过"]);
            }
            $list1 = $list;
            unset($list['vid']);
            $arr[] = $list;
            $url = Configs::get('mmadv_url');
            $send = httpPost($url, json_encode($arr));
            if ($send == 'OK') {
                $result = $this->model->insert($list1);
                if ($result) {
                    return json(["code" => 1, "msg" => "添加成功"]);
                } else {
                    return json(["code" => 0, "msg" => "添加失败"]);
                }
            }else{
                return json(["code" => 0, "msg" => "添加失败"]);
            }

        }

    }



    public function tongbu_id(){
        $list = $this->model->field('id,video_url')->select();
        foreach ($list as $k=>$v){
            $vid = Db::name('video')->where('video_url','=',$v['video_url'])->value('id');
            if($vid){

                $this->model->where('id','=',$v['id'])->setField('vid',$vid);
            }
        }
        echo 'success';

    }

    /*
 * 测试播放
 */
    public function testplay(){

        $url=input("param.url");
        $this->assign('url',$url);
        return $this->fetch();
    }
}