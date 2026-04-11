<?php
namespace app\index\controller;
use app\index\model\Point as PointModel;
use app\index\model\Configs;

class Point extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        $this->model = new PointModel();
        $this->assign('img_url', Configs::get('no_au_thumb_url')); // Reuse image URL config
    }

    public function index()
    {
        $where = [];
        $order = 'sort desc';
        $res = $this->model->listData($where, $order);
        $this->assign('list', $res['list']);
        return $this->fetch("index");
    }

    public function modify()
    {
        $post = request()->post();
        $rule = [
            'id|ID' => 'require',
            'field|字段' => 'require',
            'value|值' => 'require',
        ];
        $this->validate($post, $rule);
        $this->model->where('id', $post['id'])->update([$post['field'] => $post['value']]);
        $this->success('保存成功');
    }

    public function del()
    {
        $id = input("id");
        $this->model->where("id", $id)->delete();
        return json(["code" => 1, "msg" => "删除成功"]);
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = request()->post();
            $res = $this->model->saveData($param);
            return $res['code'] == 1 ? $this->success($res['msg']) : $this->error($res['msg']);
        }
        $info = $this->model->infoData(['id' => input('id')])['info'];
        $this->assign('info', $info);
        return $this->fetch();
    }
}