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

class Hotlist extends Model
{
    protected $table               = "hotlist_category";
    public static $translateFields = ['title', 'sub_title'];
    // protected $autoWriteTimestamp = true;
    // protected $createTime         = 'created_at';
    // protected $updateTime         = 'updated_at';

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
        if (strpos($order, '.') === false) {
            // 给排序字段添加表别名
            $safeOrder = 'h.' . $order;
        }
        
        // 处理查询字段
        $baseFields = $field == '*' ? 'h.*' : 'h.' . str_replace(',', ',h.', $field);
        
        $query = $this->alias('h')
            ->field($baseFields . ', COUNT(hcd.id) as total_video')
            ->leftJoin('hotlist_category_details hcd', 'h.id = hcd.hotlist_category_id')
            ->where($where)
            ->group('h.id')
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

    public function listData1111($where,$order,$page=1,$limit=10,$start=0,$field='*',$totalshow=1)
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