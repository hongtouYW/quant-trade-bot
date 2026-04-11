<?php

namespace app\index\model;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Group extends Model
{
    protected $table               = "video_groups";
    public static $translateFields = ['title', 'description'];

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 10, $start = 0, $field = '*', $totalshow = 1)
    {
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }

        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;
        
        // 修复排序字段歧义
        $safeOrder = $order;
        if ($order === 'id desc') {
            $safeOrder = 'g.id desc';
        } elseif ($order === 'amount desc') {
            $safeOrder = 'g.amount desc';
        } elseif ($order === 'amount asc') {
            $safeOrder = 'g.amount asc';
        }
        
        $query = $this->alias('g')
            ->field('g.*, COUNT(vgd.id) as total_video')
            ->leftJoin('video_group_details vgd', 'g.id = vgd.group_id')
            ->where($where)
            ->group('g.id')
            ->order($safeOrder);

        if ($totalshow == 1) {
            $total = $this->where($where)->count();
        }

        $list = $query->limit($limit_str)->select();

        return [
            'code' => 1,
            'msg' => '数据列表',
            'page' => $page,
            'pagecount' => $totalshow == 1 ? ceil($total / $limit) : 0,
            'limit' => $limit,
            'total' => $totalshow == 1 ? $total : 0,
            'list' => $list
        ];
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
            $where   = [];
            $where[] = ['id','=',$data['id']];
            $res     = $this->where($where)->update($data);
        }
        else{
            $res = $this->insert($data);
        }
        if(false === $res){
            return ['code'=>0,'msg'=>'保存失败：'.$this->getError() ];
        }
        return ['code'=>1,'msg'=>'保存成功'];
    }
}