<?php
namespace app\index\controller;

use app\index\model\FrontendImages as FrontendImagesModel;
use app\index\model\Configs;

class FrontendImages extends Base
{
    protected $model = '';
    
    public function initialize()
    {
        parent::initialize();
        $this->model = new FrontendImagesModel();
        $this->assign('img_url',Configs::get('no_au_thumb_url'));
    }

    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 25;
        
        $where = [];
        if(!empty($param['key'])) {
            $param['key'] = trim($param['key']);
            $where[] = ['key', 'like', '%'.$param['key'].'%'];
        }
        
        if(in_array($param['status'], ['0','1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }

        $total = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($param['page'], $param['limit'])
            ->order('sort_order desc, id asc')
            ->select();

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $param['page'],
            'limit' => $param['limit'],
        ]);
        
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        
        return $this->fetch("index");
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = input('post.');
            if(empty($param['sort_order'])) {
                $param['sort_order'] = 0;
            }
            
            $res = $this->model->saveData($param);
            if($res['code'] != 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        
        $id = input('id');
        $where = [['id', 'eq', $id]];
        $res = $this->model->infoData($where);
        
        $this->assign('info', $res['info']);
        return $this->fetch('info');
    }

    public function del()
    {
        $id = input("param.id");
        $result = $this->model->where(["id" => $id])->delete();
        if ($result) {
            return json(["code" => 1, "msg" => "删除成功"]);
        } else {
            return json(["code" => 0, "msg" => "删除失败"]);
        }
    }

    public function modify()
    {
        $this->checkPostRequest();
        $post = request()->post();
        $rule = [
            'id|ID' => 'require',
            'field|字段' => 'require',
            'value|值' => 'require',
        ];
        $this->validate($post, $rule);
        
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        
        try {
            $row->save([$post['field'] => $post['value']]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        
        $this->success('保存成功');
    }
}