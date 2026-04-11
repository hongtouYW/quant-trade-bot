<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/25
 * Time: 18:04
 */

namespace app\index\model;


use think\Model;

class Trend extends Model
{


    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function getIsRelAttr($val,$data)
    {
        $is_rel = 0;
        $count = Actor::where('tid','=',$data['id'])->count();
        if($count){
            $is_rel = 1;
        }
        return $is_rel;
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
}