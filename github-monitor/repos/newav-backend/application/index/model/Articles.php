<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/20
 * Time: 14:43
 */

namespace app\index\model;


use think\Model;

class Articles extends Model
{

/*    public function getContentAttr($val){
        $thumb_url = Configs::get('thumb_url');
        $val = preg_replace('/src="\//', 'src="'."$thumb_url".'/', $val);
        return $val;
    }*/
    public static $translateFields = ['title', 'content'];

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
            $where['id'] = ['eq',$data['id']];
            $res = $this->where($where)->update($data);
        }else{
            $res = $this->allowField(true)->insert($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }
}