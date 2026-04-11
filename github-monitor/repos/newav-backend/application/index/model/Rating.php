<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/6/7
 * Time: 14:51
 */

namespace app\index\model;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Rating extends Model
{
    protected $table = "video_reviews";

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function getUserIdAttr($val){
        $val = User::where('id','=',$val)->value('username');
        return $val;
    }

    public function getVideoIdAttr($val){
        $val = Video::where('id','=',$val)->value('title');
        return $val;
    }

    public function listData($where,$order,$page=1,$limit=10,$start=0,$field='*',$totalshow=1)
    {
        if(!is_array($where)){
            $where = json_decode($where,true);
        }

        $limit_str = ($limit * ($page-1) + $start) .",".$limit;
        if($totalshow == 1) {
            $total = $this->countData($where);
        }
        $list = $this->field($field)->where($where)->order($order)->limit($limit_str)->select();
        return ['code'=>1,'msg'=>'数据列表','page'=>$page,'pagecount'=>ceil($total/$limit),'limit'=>$limit,'total'=>$total,'list'=>$list];
    }
}