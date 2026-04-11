<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:23
 */

namespace app\index\controller;

use think\Db;

class Ticai extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Ticai();
    }


    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 25;
        $where = [];
        if (!empty($param['id'])) {
            $param['id'] = trim($param['id']);
            $where[] = ['id', '=', $param['id']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where[] = ['name', 'like', '%' . $param['name'] . '%'];
        }
        if (in_array($param['switch'], ['0', '1'], true)) {
            $where[] = ['switch', 'eq', $param['switch']];
        }
        if (in_array($param['is_top'], ['0', '1'], true)) {
            $where[] = ['is_top', 'eq', $param['is_top']];
        }

        $total = $this->model->where($where)->count();
        $list = $this->model->where($where)->page($param['page'], $param['limit'])->order('is_top desc,id asc')->select();
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
        if (Request()->isPost()) {
            $param = input('post.');
            if (empty($param['sort'])) {
                unset($param['sort']);
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
        $ticai = Db::name('ticai')->find($id);
        $translations = Db::name('ticai_trans')->where('ticai_id', $id)->select();

        $this->assign(compact('ticai', 'translations'));
        return $this->fetch();
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        // 更新 manhua_trans 表
        Db::name('ticai_trans')->where('id', $id)->update([
            'name' => $data['name'],
        ]);

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function clear()
    {
        $urls = [
            "/data/comic/ticai-en.js",
            "/data/comic/ticai-zh.js",
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

        return $this->success('清除题材缓存成功!');
    }
}
