<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:23
 */

namespace app\index\controller;

use think\Db;

class Platforms extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Platforms();
    }


    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 25;
        $where = [];
        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'], $param['limit'])->order('qudaosort desc')->select();
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


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            if (empty($param['qudaosort'])) {
                unset($param['qudaosort']);
            }
            $res = $this->model->saveData($param);
            if ($res['code'] != 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }
        $id = input('id');
        $where = [];
        $where[] = ['id', 'eq', $id];
        $res = $this->model->infoData($where);
        $this->assign('info', $res['info']);
        return $this->fetch('info');
    }


    /*
     * 删除
     */
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
            'id|ID'    => 'require',
            'field|字段' => 'require',
            'value|值'  => 'require',
        ];
        $this->validate($post, $rule);
        $row = $this->model->find($post['id']);
        if (!$row) {
            $this->error('数据不存在');
        }
        try {
            $row->save([
                $post['field'] => $post['value'],
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }

    public function edit_translation()
    {
        $id = input('id');
        $paymanager = Db::name('paymanger')->find($id);
        $translations = Db::name('paymanger_trans')->where('gateway_id', $id)->select();

        if (empty($translations)) {
            $translations = [[
                'id' => 0,
                'gateway_id' => $id,
                'lang_code' => 'en',
                'qudaoname' => '',
                'qudaodes' => '',
            ]];
        }


        $this->assign(compact('paymanager', 'translations'));
        return $this->fetch();
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::name('paymanger_trans')->insert([
                'gateway_id' => $data['gateway_id'],
                'lang_code' => $lang,
                'qudaoname' => $data['qudaoname'],
                'qudaodes' => $data['qudaodes'],
            ]);
        } else {
            // 更新翻译
            Db::name('paymanger_trans')->where('id', $id)->update([
                'qudaoname' => $data['qudaoname'],
                'qudaodes' => $data['qudaodes'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function clear()
    {
        $urls = [
            "/data/recharge/platforms-en.js",
            "/data/recharge/platforms-zh.js",
        ];

        $baseUrl = config('js_cache_clear_url');

        $client = new \GuzzleHttp\Client(['timeout' => 10]);

        try {
            foreach ($urls as $url) {
                $client->get($baseUrl . $url);
            }
        } catch (\Exception $e) {
            return $this->error("清除缓存失败：" . $e->getMessage());
        }

        return $this->success('清除缓存成功!');
    }
}
