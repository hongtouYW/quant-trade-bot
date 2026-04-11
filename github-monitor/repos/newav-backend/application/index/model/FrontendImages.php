<?php
namespace app\index\model;

class FrontendImages extends BaseModel
{
    protected $table = "frontend_images";
    
    public function saveData($data)
    {
        if(!empty($data['id'])) {
            $where = [['id', '=', $data['id']]];
            $res = $this->where($where)->update($data);
        } else {
            $res = $this->insert($data);
        }
        
        if(false === $res) {
            return ['code' => 0, 'msg' => '保存失败: '.$this->getError()];
        }
        return ['code' => 1, 'msg' => '保存成功'];
    }

    public function infoData($where, $field = '*')
    {
        if(empty($where) || !is_array($where)) {
            return ['code' => 0, 'msg' => '参数错误'];
        }
        
        $info = $this->field($field)->where($where)->find();
        if (empty($info)) {
            return ['code' => 0, 'msg' => '获取失败'];
        }
        
        return ['code' => 1, 'msg' => '获取成功', 'info' => $info];
    }
}