<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/9/14
 * Time: 15:25
 */

namespace app\index\controller;


class Feedback extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\index\model\Feedback();
    }

    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 15;

        $where = [];

        $satisfaction = input('satisfaction');
        if (!empty($satisfaction)) {
            $where[] = ['satisfaction', '=', $satisfaction];
            $this->assign('satisfaction', $satisfaction);
        }

        $search = input('search');
        if (!empty($search)) {
            $where[] = ['content|contact|ip', 'like', '%' . $search . '%'];
            $this->assign('search', $search);
        }

        $total = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($param['page'], $param['limit'])
            ->order('id desc')
            ->select();

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $param['page'],
            'limit' => $param['limit'],
            'satisfaction' => isset($satisfaction) ? $satisfaction : ''
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch();
    }

    /**
     * 查看详情 (只读)
     */
    public function info()
    {
        $id = input("id");
        $info = $this->model->where('id', $id)->find();
        $this->assign('info', $info);
        return $this->fetch();
    }

    /*
     * 删除
     */
    public function del()
    {
        $id = input("param.id");
        $result = $this->model->where('id', '=', $id)->delete();
        if ($result) {
            return json(["code" => 1, "msg" => "删除成功"]);
        } else {
            return json(["code" => 0, "msg" => "删除失败"]);
        }
    }

}