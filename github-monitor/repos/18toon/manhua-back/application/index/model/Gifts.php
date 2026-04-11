<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/30
 * Time: 15:00
 */

namespace app\index\model;

use think\Model;

class Gifts extends Model
{
    protected $name = 'gifts';
    protected $autoWriteTimestamp = false;

    /**
     * 保存数据
     * @param array $data
     * @return array
     */
    public function saveData($data)
    {
        if (!empty($data['id'])) {
            // 更新
            $data['update_time'] = time();
            $res = self::save($data, ['id' => $data['id']]);
        } else {
            // 新增
            $data['create_time'] = time();
            $res = self::create($data);
        }

        if (!$res) {
            return ['code' => 0, 'msg' => '保存失败：' . $this->getError()];
        }

        return ['code' => 1, 'msg' => '保存成功', 'id' => $res->id ?? $data['id']];
    }

    /**
     * 获取单条记录
     * @param array $where
     * @param string $field
     * @return array
     */
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
}
