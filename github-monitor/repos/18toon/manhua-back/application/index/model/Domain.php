<?php

namespace app\index\model;

use think\Model;

class Domain extends Model
{
    protected $table = 'domain';

    public function getAddTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getUpdateTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function listData($where,$order)
    {
        $list = $this->where($where)->order($order)->select();
        return ['code'=>1,'msg'=>'数据列表','list'=>$list];
    }

    public function infoData($where,$field='*')
    {
        if(empty($where) || !is_array($where)){
            return ['code'=>0,'msg'=>'参数错误'];
        }
        $info = $this->field($field)->where($where)->find();

        if(empty($info)){
            return ['code'=>0,'msg'=>'获取失败'];
        }
        return ['code'=>1,'msg'=>'获取成功','info'=>$info];
    }


    public function saveData($data)
    {
        $time = time();
        if(!empty($data['id'])){
            $where=[];
            $where[] = ['id','eq',$data['id']];
            $data['update_time'] = $time;
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $data['add_time'] = $data['update_time'] = time();
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }
}