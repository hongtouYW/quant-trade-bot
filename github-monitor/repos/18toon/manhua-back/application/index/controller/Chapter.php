<?php

/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/5/20
 * Time: 13:16
 */

namespace app\index\controller;

use app\index\model\Chapter as ChapterModel;
use app\index\model\Configs;
use app\index\model\Ticai as TicaiModel;
use app\index\model\Tags as TagsModel;
use think\cache\driver\Redis;
use think\Db;

class Chapter extends Base
{
    protected $model = '';
    protected $chapterModel = '';
    public function initialize()
    {
        parent::initialize();
        //复制模型专用  不是原模型 开始
        $this->model = new ChapterModel;
        $ticaiList = TicaiModel::field('id,name')->where('switch', '=', 1)->order('id asc')->select();
        $this->assign('ticaiList', $ticaiList);
        $tagList = TagsModel::field('id,name')->where('status', '=', 1)->order('sort desc')->select();
        $this->assign('tagList', $tagList);
    }

    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 10;
        $where = [];
        if (!empty($param['id'])) {
            $param['id'] = trim($param['id']);
            $where[] = ['id', '=', $param['id']];
        }
        if (!empty($param['wd'])) {
            $param['wd'] = trim($param['wd']);
            $where[] = ['title', 'like', '%' . $param['wd'] . '%'];
        }
        if (in_array($param['translate_status'], ['0', '1'], true)) {
            $where[] = ['translate_status', 'eq', $param['translate_status']];
        }
        if (in_array($param['translate_img'], ['0', '1', '2', '3'], true)) {
            $where[] = ['translate_img', 'eq', $param['translate_img']];
        }
        if (in_array($param['switch'], ['0', '1'], true)) {
            $where[] = ['switch', 'eq', $param['switch']];
        }
        if (in_array($param['isvip'], ['0', '1'], true)) {
            $where[] = ['isvip', 'eq', $param['isvip']];
        }
        if (!empty($param['update_time'])) {
            $param['update_time'] = str_replace('+', ' ', $param['update_time']);
            $range = explode(' - ', $param['update_time']);
            if (count($range) == 2) {
                $startTime = strtotime($range[0] . ' 00:00:00');
                $endTime = strtotime($range[1] . ' 23:59:59');
                $where[] = ['update_time', 'between', [$startTime, $endTime]];
            }
        }
        $order = 'update_time desc';
        $res = $this->model->listData($where, $order, $param['page'], $param['limit']);
        $this->assign([
            'list' => $res['list'],
            'total' => $res['total'],
            'page' => $res['page'],
            'limit' => $res['limit'],
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);
        return $this->fetch();
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
        $data = [];

        $data[$post['field']] = $post['value'];
        try {
            $row->save($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('保存成功');
    }


    public function info()
    {
        if (Request()->isPost()) {
            $param = request()->post();
            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }
            return $this->success($res['msg']);
        }

        $id = input('id');
        $where = [];
        $where['id'] = $id;
        $info = Db::name('capter')->where($where)->find();
        $this->assign('info', $info);
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

    public function clear()
    {
        $redis = new Redis();
        $keys = $redis->keys('comic*');
        if ($keys) {
            $redis->del($keys);
        }
        $this->success('清除Comic缓存成功!');
    }

    public function photos()
    {
        $id = input("param.id");
        $lang = input("param.lang", "zh");

        if ($lang === 'zh') {
            // 中文从主表获取
            $images = $this->model->where('id', '=', $id)->value('imagelist');
        } else {
            // 其他语言从翻译表获取
            $images = Db::name('capter_trans')
                ->where('capter_id', '=', $id)
                ->where('lang_code', '=', $lang)
                ->value('imagelist');
        }

        $data = [];
        if (!empty($images)) {
            $pic_url = Configs::get("IMAGE_HOST");
            $arr = explode(",", $images);
            foreach ($arr as $v) {
                $data[]['src'] = $pic_url . $v;
            }
        }
        $result["data"] = $data;
        $result["code"] = 1;
        return json($result);
    }

    public function edit_translation()
    {
        $id = input('id');
        $chapter = Db::name('capter')->find($id);
        $translations = Db::name('capter_trans')->where('capter_id', $id)->select();

        if (empty($translations)) {
            $translations = [[
                'id' => 0,
                'capter_id' => $id,
                'lang_code' => 'en',
                'title' => '',
                'imagelist' => '',
            ]];
        }

        $this->assign(compact('chapter', 'translations'));
        return $this->fetch(); // 视图页面 edit_translation.html
    }

    public function save_translation()
    {
        $data = input('');
        $lang = $data['lang_code'];
        $id = $data['id'];

        if ($id == 0) {
            // 新增翻译
            Db::name('capter_trans')->insert([
                'capter_id' => $data['capter_id'],
                'lang_code' => $lang,
                'title' => $data['title'],
                'imagelist' => $data['imagelist'],
            ]);
        } else {
            // 更新翻译
            Db::name('capter_trans')->where('id', $id)->update([
                'title' => $data['title'],
                'imagelist' => $data['imagelist'],
            ]);
        }

        return json(['code' => 0, 'msg' => '保存成功']);
    }
}
