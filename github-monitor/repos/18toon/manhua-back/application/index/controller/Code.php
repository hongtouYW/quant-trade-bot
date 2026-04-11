<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/12
 * Time: 15:14
 */

namespace app\index\controller;

use app\index\model\Code as ModelCode;
use think\Queue;

class Code extends Base
{


    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Code();
    }

    public function index()
    {

        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 20;
        $where = [];
        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'], $param['limit'])->order('id desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch("index");
    }


    public function create()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $num = (int)$param['num'];

            // 添加最大限制判断
            if ($num > 50) {
                return json(["code" => 0, "msg" => "每次最多只能生成50张兑换码"]);
            }

            $p1 = getRandChar(2, '0123456789');
            $p2 = getRandChar(3, '0123456789');
            $batch = $p1 . time() . $p2;

            $data = [
                'batch' => $batch,
                'value' => $param['value']
            ];

            for ($i = 1; $i <= $num; $i++) {
                $data['code'] = $this->create_code();
                $data['create_time'] = time();
                ModelCode::insert($data);
            }
            return json(["code" => 1, "msg" => "添加成功"]);
        }
        return $this->fetch();
    }

    private function create_code($length = 8)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $code;
    }
}
