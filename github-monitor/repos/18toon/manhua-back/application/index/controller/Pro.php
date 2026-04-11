<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/15
 * Time: 15:18
 */

namespace app\index\controller;

use app\index\model\Pro as ProModel;
use think\Db;

class Pro extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new ProModel();
    }


    public function index()
    {
        $param = input();
        $where = [];
        $order = 'sort,id';
        $res = $this->model->listData($where, $order);

        $this->assign('list', $res['list']);
        $this->assign('param', $param);
        return $this->fetch();
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = input('post.');
            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            // $this->clear();
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where = [];
        $where['id'] = ['eq', $id];

        $res = $this->model->infoData($where);

        $this->assign('info', $res['info']);

        return $this->fetch();
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
        $pro = Db::name('pro')->find($id);
        $translations = Db::name('pro_trans')->where('product_id', $id)->select();

        if (empty($translations)) {
            $translations = [[
                'id' => 0,
                'product_id' => $id,
                'lang_code' => 'en',
                'title' => '',
                'intro' => '',
            ]];
        }

        $this->assign(compact('pro', 'translations'));
        return $this->fetch();
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::name('pro_trans')->insert([
                'product_id' => $data['product_id'],
                'lang_code' => $lang,
                'title' => $data['title'],
                'intro' => $data['intro'],
            ]);
        } else {
            // 更新翻译
            Db::name('pro_trans')->where('id', $id)->update([
                'title' => $data['title'],
                'intro' => $data['intro'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function clear()
    {
        $urls = [
            "/data/recharge/lists-en.js",
            "/data/recharge/lists-zh.js",
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
