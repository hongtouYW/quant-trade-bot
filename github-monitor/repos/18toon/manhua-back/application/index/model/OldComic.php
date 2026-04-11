<?php

namespace app\index\model;

use think\Db;
use think\Model;
use think\facade\Env;

class OldComic extends Model
{
    protected $connection = 'old_ins_mh';
    protected $name = 'manhua';

    public function getTicaiNameAttr($val, $data)
    {
        return Ticai::where('id', '=', $data['ticai_id'])->value('name');
    }

    public function getTagsNameAttr($val, $data)
    {
        if (!empty($data['tags'])) {
            $val = Tags::where('id', 'in', $data['tags'])->column('name');
            $val = implode(',', $val);
        }
        return $val;
    }

    public function getImageAttr($val)
    {
        if (!empty($val)) {
            $image_url = Configs::get('IMAGE_HOST');
            $val = $image_url . $val;
        }
        return $val;
    }

    public function getCoverAttr($val)
    {
        if (!empty($val)) {
            $image_url = Configs::get('IMAGE_HOST');
            $val = $image_url . $val;
        }
        return $val;
    }

    public function getUpdateTimeAttr($val)
    {
        return date('Y-m-d H:i:s', $val);
    }

    public function countData($where)
    {
        $total = $this->where($where)->count();
        return $total;
    }

    public function listData($where, $order, $page = 1, $limit = 10, $start = 0, $field = '*', $totalshow = 1)
    {
        // 动态读取数据库名与前缀
        $mainDb = Env::get('database.database', 'ins_mh');
        $oldDb = Env::get('old_database.database', 'xin_uu');
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

        $offset = max(0, $limit * ($page - 1) + $start);
        $limit_str = "$offset,$limit";

        // 章节数子查询
        $migratedSub = Db::table("$mainDb.{$prefix}capter")
            ->field('manhua_id, COUNT(*) AS migrated_count')
            ->group('manhua_id')
            ->buildSql();
        $newSub = Db::table("$oldDb.{$prefix}capter")
            ->field('manhua_id, COUNT(*) AS new_count')
            ->group('manhua_id')
            ->buildSql();

        // Step 1: 查询分页ID
        $idQuery = OldComic::alias('m')
            ->join("$mainDb.{$prefix}manhua c", 'c.original_id = m.id', 'LEFT');

        if (!empty($where)) {
            $idQuery->where($where);
        }

        if ($where2) {
            $idQuery->where($where2);
        }

        $idQuery->field('m.id');

        // 如果有筛选搬迁状态，这里也要拼接对应的 whereRaw
        if ($translateFilter !== null) {
            $idQuery
                ->join([$migratedSub => 'ch'], 'ch.manhua_id = c.id', 'LEFT')
                ->join([$newSub => 'nch'], 'nch.manhua_id = m.id', 'LEFT');

            if ($translateFilter === '2') {
                $idQuery->whereRaw('ch.migrated_count = nch.new_count AND nch.new_count != 0');
            } elseif ($translateFilter === '0') {
                $idQuery->whereRaw('(ch.migrated_count IS NULL OR ch.migrated_count = 0) AND nch.new_count != 0');
            } elseif ($translateFilter === '1') {
                $idQuery->whereRaw('ch.migrated_count > 0 AND ch.migrated_count < nch.new_count');
            }
        }

        $idList = $idQuery->order($order)->limit($limit_str)->column('m.id');

        if (empty($idList)) {
            return [
                'code' => 1,
                'msg' => '数据列表',
                'page' => $page,
                'pagecount' => 0,
                'limit' => $limit,
                'total' => 0,
                'list' => []
            ];
        }

        // Step 2: 查询具体数据
        $query = OldComic::alias('m')
            ->join("$mainDb.{$prefix}manhua c", 'c.original_id = m.id', 'LEFT')
            ->join([$migratedSub => 'ch'], 'ch.manhua_id = c.id', 'LEFT')
            ->join([$newSub => 'nch'], 'nch.manhua_id = m.id', 'LEFT')
            ->whereIn('m.id', $idList)
            ->field([
                'm.*',
                'IFNULL(ch.migrated_count, 0) AS migrated_count',
                'IFNULL(nch.new_count, 0) AS new_count',
            ]);

        $list = $query->select();

        // Step 3: 查询总数
        $total = 0;
        if ($totalshow == 1) {
            $totalQuery = OldComic::alias('m')
                ->join("$mainDb.{$prefix}manhua c", 'c.original_id = m.id', 'LEFT')
                ->join([$migratedSub => 'ch'], 'ch.manhua_id = c.id', 'LEFT')
                ->join([$newSub => 'nch'], 'nch.manhua_id = m.id', 'LEFT');

            if (!empty($where)) {
                $totalQuery->where($where);
            }

            if ($where2) {
                $totalQuery->where($where2);
            }

            if ($translateFilter === '2') {
                $totalQuery->whereRaw('ch.migrated_count = nch.new_count AND nch.new_count != 0');
            } elseif ($translateFilter === '0') {
                $totalQuery->whereRaw('(ch.migrated_count IS NULL OR ch.migrated_count = 0) AND nch.new_count != 0');
            } elseif ($translateFilter === '1') {
                $totalQuery->whereRaw('ch.migrated_count > 0 AND ch.migrated_count < nch.new_count');
            }

            $total = $totalQuery->count('DISTINCT m.id');
        }

        return [
            'code' => 1,
            'msg' => '数据列表',
            'page' => $page,
            'pagecount' => ceil($total / $limit),
            'limit' => $limit,
            'total' => $total,
            'list' => $list,
        ];
    }

    // public function listData($where, $order, $page = 1, $limit = 10, $start = 0, $field = '*', $totalshow = 1)
    // {
    //     if (!is_array($where)) {
    //         $where = json_decode($where, true);
    //     }
    //     $where2 = '';
    //     if (!empty($where['_string'])) {
    //         $where2 = $where['_string'];
    //         unset($where['_string']);
    //     }

    //     $translateFilter = $where['migrate'] ?? null;
    //     unset($where['migrate']);

    //     $offset = max(0, $limit * ($page - 1) + $start);
    //     $limit_str = "$offset,$limit";

    //     // 总数查询
    //     $total = 0;
    //     if ($totalshow == 1) {
    //         $totalQuery = OldComic::alias('m')
    //             ->join('ins_mh_cn.qiswl_manhua c', 'c.original_id = m.id', 'LEFT')
    //             ->join('ins_mh_cn.qiswl_capter ch', 'ch.manhua_id = c.id', 'LEFT')
    //             ->join('new_manhua.qiswl_capter nch', 'nch.manhua_id = m.id', 'LEFT')
    //             ->field([
    //                 'm.*',
    //                 'COUNT(DISTINCT ch.id) as migrated_count',
    //                 'COUNT(DISTINCT nch.id) as new_count'
    //             ])
    //             ->group('m.id');

    //         if ($translateFilter === '2') {
    //             $totalQuery->having('migrated_count = new_count AND new_count != 0');
    //         } elseif ($translateFilter === '0') {
    //             $totalQuery->having('migrated_count != new_count AND new_count != 0');
    //         } elseif ($translateFilter === '1') {
    //             $totalQuery->having('migrated_count > 0 AND migrated_count < new_count');
    //         }
    //         $total = $totalQuery->count();
    //     }

    //     // 主查询
    //     $query = OldComic::alias('m')
    //         ->join('ins_mh_cn.qiswl_manhua c', 'c.original_id = m.id', 'LEFT')
    //         ->join('ins_mh_cn.qiswl_capter ch', 'ch.manhua_id = c.id', 'LEFT')
    //         ->join('new_manhua.qiswl_capter nch', 'nch.manhua_id = m.id', 'LEFT')
    //         ->field([
    //             'm.*',
    //             'COUNT(DISTINCT ch.id) as migrated_count',
    //             'COUNT(DISTINCT nch.id) as new_count'
    //         ])
    //         ->group('m.id');

    //     if ($translateFilter === '2') {
    //         $query->having('migrated_count = new_count AND new_count != 0');
    //     } elseif ($translateFilter === '0') {
    //         $query->having('migrated_count != new_count AND new_count != 0');
    //     } elseif ($translateFilter === '1') {
    //         $query->having('migrated_count > 0 AND migrated_count < new_count');
    //     }

    //     $list = $query->order($order)
    //         ->limit($limit_str)
    //         ->select();

    //     return [
    //         'code' => 1,
    //         'msg' => '数据列表',
    //         'page' => $page,
    //         'pagecount' => ceil($total / $limit),
    //         'limit' => $limit,
    //         'total' => $total,
    //         'list' => $list
    //     ];
    // }

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
