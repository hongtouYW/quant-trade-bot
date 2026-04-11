<?php

namespace app\index\model;

use app\service\ElasticsearchService;
use think\facade\Env;
use think\Db;
use think\Model;

class Comic extends Model
{
    protected $name = 'manhua';
    


    protected static function init()
    {
        self::afterWrite(function ($comic) {
            $fullComic = self::find($comic['id']);

            ElasticsearchService::indexManhua([
                'id' => $comic['id'],
                'title' => $comic['title'] ?? $fullComic['title'],
                'desc' => $comic['desc'] ?? $fullComic['desc'],
            ], 'zh');
        });

        self::afterDelete(function ($comic) {
            ElasticsearchService::deleteManhua($comic['id'], 'zh');
        });
    }

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
        if (!is_array($where)) {
            $where = json_decode($where, true);
        }
        $where2 = '';
        if (!empty($where['_string'])) {
            $where2 = $where['_string'];
            unset($where['_string']);
        }

        // -------------------------------
        // 提取过滤条件
        // -------------------------------
        $capterTranslateFilter = null;
        $capterAuditFilter = null;

        foreach ($where as $key => $condition) {
            if ($condition[0] == 'capter_img_translate') {
                $capterTranslateFilter = $condition[2];
                unset($where[$key]);
            }
            if ($condition[0] == 'capter_audit') {
                $capterAuditFilter = $condition[2];
                unset($where[$key]);
            }
        }

        // -------------------------------
        // 子查询定义
        // -------------------------------
        // 翻译完成数
        $translatedSub = Db::name('capter')
            ->field('manhua_id, COUNT(*) AS translated_count')
            ->where('translate_img', '=', 2)
            ->group('manhua_id')
            ->buildSql();

        // 总章节数
        $totalSub = Db::name('capter')
            ->field('manhua_id, COUNT(*) AS total_count')
            ->group('manhua_id')
            ->buildSql();

        // 已审核章节数
        $auditSub = Db::name('capter')
            ->field('manhua_id, COUNT(*) AS audited_count')
            ->where('audit_status', '=', 1)
            ->group('manhua_id')
            ->buildSql();

        // -------------------------------
        // 分页计算
        // -------------------------------
        $offset = max(0, $limit * ($page - 1) + $start);
        $limit_str = "$offset,$limit";

        // -------------------------------
        // Step 1: 查询分页 ID
        // -------------------------------
        $idQuery = $this->alias('m');
        if (!empty($where)) {
            $idQuery->where($where);
        }
        if ($where2) {
            $idQuery->where($where2);
        }

        if ($capterTranslateFilter !== null || $capterAuditFilter !== null) {
            $idQuery->leftJoin([$totalSub => 't'], 't.manhua_id = m.id');
        }

        // 应用翻译过滤
        if ($capterTranslateFilter !== null) {
            $idQuery->leftJoin([$translatedSub => 'tc'], 'tc.manhua_id = m.id');

            if ($capterTranslateFilter === '1') {
                $idQuery->whereRaw('tc.translated_count > 0 AND tc.translated_count < t.total_count');
            } elseif ($capterTranslateFilter === '2') {
                $idQuery->whereRaw('tc.translated_count = t.total_count AND t.total_count > 0');
            } elseif ($capterTranslateFilter === '0') {
                $idQuery->whereRaw('tc.translated_count IS NULL OR tc.translated_count = 0');
            }
        }

        // 应用审核过滤
        if ($capterAuditFilter !== null) {
            $idQuery->leftJoin([$auditSub => 'ac'], 'ac.manhua_id = m.id');

            if ($capterAuditFilter === '1') {
                $idQuery->whereRaw('ac.audited_count > 0 AND ac.audited_count < t.total_count');
            } elseif ($capterAuditFilter === '2') {
                $idQuery->whereRaw('ac.audited_count = t.total_count AND t.total_count > 0');
            } elseif ($capterAuditFilter === '0') {
                $idQuery->whereRaw('ac.audited_count IS NULL OR ac.audited_count = 0');
            }
        }

        $idList = $idQuery->field('m.id')->order($order)->limit($limit_str)->column('m.id');

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

        // -------------------------------
        // Step 2: 查询具体数据
        // -------------------------------
        $mainDb = Env::get('database.database', 'ins_mh');

        $query = $this->alias('m')
            ->leftJoin([$translatedSub => 'tc'], 'tc.manhua_id = m.id')
            ->leftJoin([$totalSub => 't'], 't.manhua_id = m.id')
            ->leftJoin([$auditSub => 'ac'], 'ac.manhua_id = m.id')
            ->leftJoin(["{$mainDb}.admin" => 'a'], 'a.id = m.admin_id')
            ->whereIn('m.id', $idList)
            ->field([
                'm.*',
                'IFNULL(tc.translated_count, 0) AS translated_count',
                'IFNULL(t.total_count, 0) AS total_count',
                'IFNULL(ac.audited_count, 0) AS audited_count',
                'a.username as admin_username',
            ]);

        $list = $query->order($order)->select();

        // -------------------------------
        // Step 3: 查询总数
        // -------------------------------
        $total = 0;
        if ($totalshow == 1) {
            $totalQuery = $this->alias('m');
            if (!empty($where)) {
                $totalQuery->where($where);
            }
            if ($where2) {
                $totalQuery->where($where2);
            }

            if ($capterTranslateFilter !== null || $capterAuditFilter !== null) {
                $totalQuery->leftJoin([$totalSub => 't'], 't.manhua_id = m.id');
            }


            if ($capterTranslateFilter !== null) {
                $totalQuery->leftJoin([$translatedSub => 'tc'], 'tc.manhua_id = m.id');

                if ($capterTranslateFilter === '1') {
                    $totalQuery->whereRaw('tc.translated_count > 0 AND tc.translated_count < t.total_count');
                } elseif ($capterTranslateFilter === '2') {
                    $totalQuery->whereRaw('tc.translated_count = t.total_count AND t.total_count > 0');
                } elseif ($capterTranslateFilter === '0') {
                    $totalQuery->whereRaw('tc.translated_count IS NULL OR tc.translated_count = 0');
                }
            }

            if ($capterAuditFilter !== null) {
                $totalQuery->leftJoin([$auditSub => 'ac'], 'ac.manhua_id = m.id');

                if ($capterAuditFilter === '1') {
                    $totalQuery->whereRaw('ac.audited_count > 0 AND ac.audited_count < t.total_count');
                } elseif ($capterAuditFilter === '2') {
                    $totalQuery->whereRaw('ac.audited_count = t.total_count AND t.total_count > 0');
                } elseif ($capterAuditFilter === '0') {
                    $totalQuery->whereRaw('ac.audited_count IS NULL OR ac.audited_count = 0');
                }
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
            $data['admin_id'] = session('admin_id') ?: 0;
            $res = self::save($data, ['id' => $data['id']]);
        }
        if (false === $res) {
            return ['code' => 0, 'msg' => '保存失败：' . $this->getError()];
        }
        return ['code' => 1, 'msg' => '保存成功'];
    }
}
