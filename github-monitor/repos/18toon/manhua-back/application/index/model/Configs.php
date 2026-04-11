<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/31
 * Time: 13:13
 */

namespace app\index\model;


use app\lib\exception\BaseException;
use think\Model;

class Configs extends Model
{

    protected $name='config';

    public function saveData($data)
    {
        if(!empty($data['id'])){
            $where=[];
            $where[] = ['id','=',$data['id']];
            $data['update_time'] = date('Y-m-d H:i:s');
            $res = $this->where($where)->update($data);
        }
        else{

            $count = $this->where('name','=',$data['name'])->count();
            if($count){
                return ['code'=>0,'msg'=>'名称已存在'];
            }
            $data['create_time'] = $data['update_time'] = date('Y-m-d H:i:s');
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

    public static function get($name)
    {
        $results = self::where("name",'=',$name)->value('value');
        return $results;
    }

}