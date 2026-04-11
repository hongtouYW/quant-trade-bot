<?php
namespace app\index\model;

use think\Model;

class HomepageModules extends Model
{
    protected $name = 'homepage_modules';

    public function saveData($data)
    {
        if (!empty($data['id'])) {
            $res = $this->allowField(true)->save($data, ['id' => $data['id']]);
            $id = $data['id'];
        } else {
            $res = $this->allowField(true)->save($data);
            $id = $this->id; 
        }

        return $res
            ? ['code' => 1, 'msg' => '保存成功', 'id' => $id]
            : ['code' => 0, 'msg' => '保存失败'];
    }

    public function infoData($where)
    {
        return ['code' => 1, 'info' => $this->where($where)->find()];
    }
}
