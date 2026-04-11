<?php

namespace app\index\model;

use think\Model;

class Version extends Model
{

    protected $table = 'version';

    public function saveData($data)
    {
        if(!empty($data['id'])){
            $where=[];
            $where[] = ['id','=',$data['id']];
            $data['update_time'] = time();
            $res = $this->where($where)->update($data);
        }
        else{
            $data['add_time'] = $data['update_time'] = time();
            $res = $this->insert($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
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


    public function getAddTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getUpdateTimeAttr($value){
        return date('Y-m-d H:i:s',$value);
    }
}