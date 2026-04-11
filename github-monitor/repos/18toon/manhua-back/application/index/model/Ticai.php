<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:28
 */

namespace app\index\model;

use think\Queue;

class Ticai extends BaseModel
{

    protected $name = "ticai";
    
    public function saveData($data)
    {
        if (!empty($data['id'])) {
            $where = [['id', '=', $data['id']]];
            $res = $this->where($where)->update($data);
            $id = $data['id'];
        } else {
            $id = $this->insertGetId($data); // 获取插入的 ID
            $res = $id ? true : false;
        }

        if (false === $res) {
            return ['code' => 0, 'msg' => '保存失败' . $this->getError()];
        }

        Queue::push('app\index\job\TranslateInit', ['type' => 'ticai', 'type_id' => $id, 'fields' => ['name'], 'languages' => ['en']]);

        return ['code' => 1, 'msg' => '保存成功'];
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
