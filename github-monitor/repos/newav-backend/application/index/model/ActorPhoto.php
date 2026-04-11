<?php
namespace app\index\model;

use think\Model;

class ActorPhoto extends Model
{
    protected $name = 'actor_photos';

    public function saveData($data)
    {
        if (!empty($data['id'])) {
            $res = $this->where(['id' => $data['id']])->update($data);
        } else {
            $res = $this->insert($data);
        }

        if ($res === false) {
            return ['code'=>0, 'msg'=>'保存失败'];
        }
        return ['code'=>1, 'msg'=>'保存成功'];
    }

    public function listData($actor_id)
    {
        return $this->where('actor_id', $actor_id)
                    ->order('sort desc,id desc')
                    ->select();
    }
}
