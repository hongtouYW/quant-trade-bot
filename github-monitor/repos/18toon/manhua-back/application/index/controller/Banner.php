<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/22
 * Time: 19:01
 */

namespace app\index\controller;

use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Db;

class Banner extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Banner();
        $position = config('banner.');
        $this->assign('position', $position);
    }


    //主页
    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 15;
        $where = [];

        if (!empty($param['position'])) {
            $where[] = ['position', 'eq', $param['position']];
        }
        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }
        $total = $this->model->where($where)->count();
        $list =  $this->model->where($where)->page($param['page'], $param['limit'])->order('sort desc')->select();
        $this->assign([
            'list'  => $list,
            'total' => $total,
            'page'  => $param['page'],
            'limit' => $param['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';

        $img_domain = Configs::get('IMAGE_HOST');

        $this->assign('param', $param);
        $this->assign('img_domain', $img_domain);
        return $this->fetch("index");
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = request()->post();

            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }

            $bannerId = $res['id'];

            // ✅ 保存多语言翻译
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {
                    $transData = [
                        'banner_id' => $bannerId,
                        'lang_code' => $lang,
                        'image' => $trans['image'] ?? '',
                    ];

                    insertOrUpdate('qiswl_banner_trans', ['banner_id' => $bannerId, 'lang_code' => $lang], $transData);
                }
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $info = Db::name('banner')->where('id', $id)->find();

        // 你系统支持的语言
        $allLangs = config('app.supported_lang');

        // 数据库已有翻译
        $translationsFromDb = Db::name('banner_trans')
            ->where('banner_id', $id)
            ->column('*', 'lang_code'); // 以 lang_code 为 key

        // 补全缺少的语言
        $translations = [];
        foreach ($allLangs as $langCode) {
            if (isset($translationsFromDb[$langCode])) {
                $translations[] = $translationsFromDb[$langCode];
            } else {
                $translations[] = [
                    'lang_code' => $langCode,
                    'image'     => '',
                ];
            }
        }

        $this->assign(compact('info', 'translations'));
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

    public function clear()
    {
        $redis = new Redis();
        $keys = $redis->keys('banner*');
        if ($keys) {
            $redis->del($keys);
        }
        $this->success('清除Banner缓存成功!');
    }

    public function edit_translation()
    {
        $id = input('id');
        $banner = Db::name('banner')->find($id);
        $translations = Db::name('banner_trans')->where('banner_id', $id)->select();

        if (empty($translations)) {
            $translations = [[
                'id' => 0,
                'banner_id' => $id,
                'lang_code' => 'en',
                'image' => '',
            ]];
        }

        $img_domain = Configs::get('IMAGE_HOST');

        $this->assign(compact('banner', 'translations', 'img_domain'));
        return $this->fetch();
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::name('banner_trans')->insert([
                'banner_id' => $data['banner_id'],
                'lang_code' => $lang,
                'image' => $data['image'],
            ]);
        } else {
            // 更新翻译
            Db::name('banner_trans')->where('id', $id)->update([
                'image' => $data['image'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }
}
