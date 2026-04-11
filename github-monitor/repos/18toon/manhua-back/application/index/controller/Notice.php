<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/1/13
 * Time: 0:06
 */

namespace app\index\controller;

use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Db;

class Notice extends Base
{
    protected $model = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new \app\index\model\Notice();
    }

    public function index()
    {

        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;
        $where = [];
        if (in_array($param['is_show'], ['0', '1'], true)) {
            $where[] = ['is_show', 'eq', $param['is_show']];
        }
        $total = $this->model->where($where)->count();
        $list = $this->model->where($where)->page($param['page'], $param['limit'])->order('sort desc,id desc')->select();
        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $param['page'],
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

            if (isset($param['start_time']) && $param['start_time'] !== '') {
                if ($param['start_time'] == '0') {
                    $param['start_time'] = 0;
                } else {
                    // 强制加上 00:00:00
                    $param['start_time'] = strtotime($param['start_time'] . ' 00:00:00');
                }
            }

            if (isset($param['end_time']) && $param['end_time'] !== '') {
                if ($param['end_time'] == '0') {
                    $param['end_time'] = 0;
                } else {
                    // 强制加上 23:59:59
                    $param['end_time'] = strtotime($param['end_time'] . ' 23:59:59');
                }
            }

            $noticeId = $res['id'];
            // ✅ 保存多语言翻译
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {
                    $transData = [
                        'notice_id' => $noticeId,
                        'lang_code' => $lang,
                        'title' => $trans['title'] ?? '',
                        'content' => $trans['content'] ?? '',
                        'image' => $trans['image'] ?? '',
                        'mobile_image' => $trans['mobile_image'] ?? '',
                    ];
                    insertOrUpdateOrigin('notice_trans', ['notice_id' => $noticeId, 'lang_code' => $lang], $transData);
                }
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $info = Db::table('notice')->where('id', $id)->find();

        if ($info) {
            if (!empty($info['start_time'])) {
                $info['start_time'] = $info['start_time'] == 0
                    ? ''
                    : date('Y-m-d', $info['start_time']);
            } else {
                $info['start_time'] = '';
            }

            if (!empty($info['end_time'])) {
                $info['end_time'] = $info['end_time'] == 0
                    ? ''
                    : date('Y-m-d', $info['end_time']);
            } else {
                $info['end_time'] = '';
            }
        }

        // 你系统支持的语言
        $allLangs = config('app.supported_lang');

        // 数据库已有翻译
        $translationsFromDb = Db::table('notice_trans')
            ->where('notice_id', $id)
            ->column('*', 'lang_code'); // 以 lang_code 为 key

        // 补全缺少的语言
        $translations = [];
        foreach ($allLangs as $langCode) {
            if (isset($translationsFromDb[$langCode])) {
                $translations[] = $translationsFromDb[$langCode];
            } else {
                $translations[] = [
                    'lang_code' => $langCode,
                    'title' => '',
                    'content' => '',
                    'image' => '',
                ];
            }
        }

        $this->assign(compact('info', 'translations'));
        return $this->fetch('info');
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

    public function edit_translation()
    {
        $id = input('id');
        $notice = Db::table('notice')->find($id);
        $translations = Db::table('notice_trans')->where('notice_id', $id)->select();

        if (empty($translations)) {
            $translations = [
                [
                    'id' => 0,
                    'notice_id' => $id,
                    'lang_code' => 'en',
                    'title' => '',
                    'content' => '',
                    'image' => '',
                ]
            ];
        }

        $img_domain = Configs::get('IMAGE_HOST');

        $this->assign(compact('notice', 'translations', 'img_domain'));
        return $this->fetch();
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::table('notice_trans')->insert([
                'notice_id' => $data['notice_id'],
                'lang_code' => $lang,
                'title' => $data['title'],
                'content' => $data['content'],
                'image' => $data['image'],
                'mobile_image' => $data['mobile_image'],
            ]);
        } else {
            // 更新翻译
            Db::table('notice_trans')->where('id', $id)->update([
                'title' => $data['title'],
                'content' => $data['content'],
                'image' => $data['image'],
                'mobile_image' => $data['mobile_image'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }

    public function clear()
    {
        $redis = new Redis();
        $keys = $redis->keys('notice*');
        if ($keys) {
            $redis->del($keys);
        }
        $this->success('清除Notice缓存成功!');
    }

}
