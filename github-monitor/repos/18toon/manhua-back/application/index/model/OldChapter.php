<?php

namespace app\index\model;

use think\Model;
use think\facade\Env;

class OldChapter extends Model
{
    protected $connection = 'old_ins_mh';
    protected $name = 'capter';

    public function getUpdateTimeAttr($val)
    {
        return date('Y-m-d H:i:s', $val);
    }

    public function getImageAttr($val)
    {
        if (!empty($val)) {
            $image_url = Configs::get('IMAGE_HOST');
            $val = $image_url . $val;
        }
        return $val;
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 10, $start = 0, $field = '*', $totalshow = 1)
    {
        $mainDb = Env::get('database.database', 'ins_mh');
        $prefix = Env::get('cn_database.prefix', 'qiswl_');

        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $where2 = '';
        if (!empty($where['_string'])) {
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        $translateFilter = $where['migrate'] ?? null;
        unset($where['migrate']);

        $limit_str = ($limit * ($page - 1) + $start) . "," . $limit;

        $chapterTable = "`{$mainDb}`.`{$prefix}capter`";
        $existsSql = "EXISTS (SELECT 1 FROM {$chapterTable} c WHERE c.original_id = oc.id)";
        $notExistsSql = "NOT EXISTS (SELECT 1 FROM {$chapterTable} c WHERE c.original_id = oc.id)";

        $baseQuery = OldChapter::alias('oc')->where($where);

        if (!empty($where2)) {
            $baseQuery->where($where2);
        }

        if ($translateFilter === '1') {
            $baseQuery->whereRaw($existsSql);
        } elseif ($translateFilter === '0') {
            $baseQuery->whereRaw($notExistsSql);
        }

        $total = $totalshow == 1 ? (clone $baseQuery)->count() : 0;

        $list = $baseQuery
            ->field([
                'oc.*',
                "{$existsSql} AS is_migrated"
            ])
            ->order($order)
            ->limit($limit_str)
            ->select();

        // $list = $this->field($field)->where($where)->where($where2)->order($order)->limit($limit_str)->select();

        return [
            'code' => 1,
            'msg' => '数据列表',
            'page' => $page,
            'pagecount' => ceil($total / $limit),
            'limit' => $limit,
            'total' => $total,
            'list' => $list
        ];
    }

    public function infoData($where, $field = '*')
    {
        if (empty($where) || !is_array($where)) {
            return ['code' => 0, 'msg' => '参数错误'];
        }

        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 0, 'msg' => '获取失败'];
        }

        return ['code' => 1, 'msg' => '获取成功', 'info' => $info];
    }

    public function saveData($data)
    {
        if (!empty($data['id'])) {
            $where = [];
            $where[] = ['id', '=', $data['id']];
            $res = $this->where($where)->update($data);
        }
        if (false === $res) {
            return ['code' => 0, 'msg' => '保存失败：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => '保存成功'];
    }
}
