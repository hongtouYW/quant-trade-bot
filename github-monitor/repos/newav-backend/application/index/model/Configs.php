<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/31
 * Time: 13:13
 */

namespace app\index\model;


use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Model;

class Configs extends Model
{

    public function saveData($data)
    {
        if(!empty($data['id'])){

            $admin_name=session('admin_name');
            if($admin_name != 'bestadmin' && $data['id'] == 6){
                return ['code'=>0,'msg'=>'暂无权限'];
            }

            $where=[];
            $where[] = ['id','=',$data['id']];
            $data['update_at'] = time();
            $res = $this->where($where)->update($data);
        }
        else{

            $count = $this->where('name','=',$data['name'])->count();
            if($count){
                return ['code'=>0,'msg'=>'名称已存在'];
            }
            $data['create_at'] = $data['update_at'] = time();
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


    public function getCreateAtAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public function getUpdateAtAttr($value){
        return date('Y-m-d H:i:s',$value);
    }

    public static function get($name)
    {

        //Config save to redis
        $redis = new Redis();
        $redis_key = 'config_'.$name;
        $results = $redis->get($redis_key);

        if(!$results)
        {
            $results = self::where("name",'=',$name)->value('value');
            //save cache
            $redis->set($redis_key, $results,86400);
        }
        return $results;
    }

}