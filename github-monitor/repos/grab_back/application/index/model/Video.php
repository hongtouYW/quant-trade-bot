<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/6/7
 * Time: 14:51
 */

namespace app\index\model;
use think\Model;

class Video extends Model
{

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }



    public function getTagsNameAttr($val,$data){
        if(!empty($data['tags'])){
            $val = Tags::where('id','in',$data['tags'])->column('name');
            $val = implode($val,',');
        }
        return $val;
    }


    public function getThumbAttr($val){

        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }


    public function getVideoUrlAttr($val){

        if(!empty($val)){
            $video_url = Configs::get('video_url');
            $val = $video_url.$val;
        }
        return $val;
    }

    public function listData($where,$order,$page=1,$limit=10,$start=0,$field='*',$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }
        $where2='';
        if(!empty($where['_string'])){
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow==1) {
            $total = $this->countData($where);
        }
        $list = $this->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();
        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>0,'msg'=>'参数错误'];
        }

        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 0, 'msg' => '获取失败'];
        }

        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
    }


    public function saveData($data)
    {
        if(!empty($data['id'])){
            $where=[];
            $where[] = ['id','=',$data['id']];
            $res = $this->where($where)->update($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }

}