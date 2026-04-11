<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/22
 * Time: 19:03
 */

namespace app\index\model;


use think\Model;

class Banner extends Model
{
    protected $autoWriteTimestamp = false;

    public function saveData($data)
    {

        if(!empty($data['id'])){
            $where=[];
            $where[] = ['id','=',$data['id']];
            $res = $this->allowField(true)->where($where)->update($data);
        }
        else{
            $res = $this->allowField(true)->insert($data);
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
}