<?php

namespace app\index\model;

use think\Model;

class Pcads extends Model
{
    protected $table = 'pc_ads';

    public function getPositionTextAttr($val,$data)
    {
        $arr = [1=>'首页底部横幅','2'=>'底部悬浮',3=>'列表页',4=>'播放页',5=>'首页弹窗',6=>'播放页2'];
        return $arr[$data['position']];
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
        if(!empty($data['id'])){
            $where=[];
            $where['id'] = ['eq',$data['id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }
}