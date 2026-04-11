<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/13
 * Time: 2:07
 */

namespace app\index\model;


use think\Model;

class Notice extends Model
{
    protected $table = 'notice';
    public function getAddTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }

    public function getUpdateTimeAttr($value)
    {
        return date('Y-m-d H:i:s', $value);
    }

    public function getStartTimeAttr($value)
    {
        return $value ? date('Y-m-d', $value) : '';
    }

    public function getEndTimeAttr($value)
    {
        return $value ? date('Y-m-d', $value) : '';
    }

    public function saveData($data)
    {
        $time = time();

        // 处理 start_time
        if (!empty($data['start_time']) && !is_numeric($data['start_time'])) {
            $data['start_time'] = strtotime($data['start_time']);
        }
        // 处理 end_time
        if (!empty($data['end_time']) && !is_numeric($data['end_time'])) {
            $data['end_time'] = strtotime($data['end_time']);
        }

        if (!empty($data['id'])) {
            $data['update_time'] = $time;
            $res = self::save($data, ['id' => $data['id']]);
        } else {
            $data['add_time'] = $data['update_time'] = $time;
            $res = self::create($data);
        }

        if (!$res) {
            return ['code' => 0, 'msg' => '保存失败：' . $this->getError()];
        }

        return ['code' => 1, 'msg' => '保存成功', 'id' => $res->id ?? $data['id']];
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
}
