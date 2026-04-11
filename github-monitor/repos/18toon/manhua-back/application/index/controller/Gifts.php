<?php
namespace app\index\controller;
use app\index\model\Pro;
use app\index\model\Configs;
use app\index\model\Gifts as GiftsModel;
use think\cache\driver\Redis;

use think\Db;

class Gifts extends Base
{
    protected $model;

    public function initialize()
    {
        parent::initialize();
        $this->model = new GiftsModel();
    }

    public function index()
    {
        $param = input();
        $param['page'] = !empty($param['page']) ? $param['page'] : 1;
        $param['limit'] = !empty($param['limit']) ? $param['limit'] : 20;

        $where = [];

        // 状态筛选
        if (in_array($param['status'], ['0', '1'], true)) {
            $where[] = ['status', 'eq', $param['status']];
        }

        // 活动名称搜索
        if (!empty($param['keyword'])) {
            $where[] = ['name', 'like', "%{$param['keyword']}%"];
        }

        // 按绑定商品ID搜索
        if (!empty($param['pro_id'])) {
            $where[] = ['pro_id', '=', $param['pro_id']];
        }

        $total = $this->model->where($where)->count();
        $list = $this->model->where($where)
            ->page($param['page'], $param['limit'])
            ->order('id desc')
            ->select();

        $products = Db::name('pro')
            ->field('id,title,intro')
            ->select();

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $param['page'],
            'limit' => $param['limit'],
            'products' => $products,
        ]);
        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        return $this->fetch("index");
    }

    public function info()
    {
        if (request()->isPost()) {
            $param = request()->post();

            // 时间处理
            if (!empty($param['start_time'])) {
                $param['start_time'] = strtotime($param['start_time']);
            }
            if (!empty($param['end_time'])) {
                $param['end_time'] = strtotime($param['end_time']);
            }

            // ✅ 如果要开启，检查是否有重叠的活动
            if (isset($param['status']) && $param['status'] == 1) {
                if ($this->hasOverlap($param)) {
                    return json(['code' => 0, 'msg' => '存在时间重叠的活动，无法保存']);
                }
            }

            // ✅ 保存主表
            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }

            $giftId = $res['id'];

            // ✅ 保存多语言翻译
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {
                    $transData = [
                        'gift_id' => $giftId,
                        'lang_code' => $lang,
                        'name' => $trans['name'] ?? '',
                    ];

                    insertOrUpdateOrigin(
                        'qiswl_gifts_trans',
                        ['gift_id' => $giftId, 'lang_code' => $lang],
                        $transData
                    );
                }
            }

            return $this->success($res['msg']);
        }

        $id = input('id/d');
        $info = [];
        if ($id) {
            $info = $this->model->where('id', $id)->find();
            if (!empty($info['start_time'])) {
                $info['start_time'] = date('Y-m-d', $info['start_time']);
            }
            if (!empty($info['end_time'])) {
                $info['end_time'] = date('Y-m-d', $info['end_time']);
            }
        }

        // 系统支持的语言
        $allLangs = config('app.supported_lang');

        // 数据库已有翻译
        $translationsFromDb = Db::name('gifts_trans')
            ->where('gift_id', $id)
            ->column('*', 'lang_code');

        // 补全缺少的语言
        $translations = [];
        foreach ($allLangs as $langCode) {
            if (isset($translationsFromDb[$langCode])) {
                $translations[] = $translationsFromDb[$langCode];
            } else {
                $translations[] = [
                    'lang_code' => $langCode,
                    'name' => '',
                ];
            }
        }

        $products = Pro::where('status', 1)
            ->field('id,title,intro')
            ->order('sort desc,id asc')
            ->select();

        $this->assign(compact('info', 'translations', 'products'));
        return $this->fetch('info');
    }

    public function del()
    {
        $id = input("param.id");
        $res = $this->model->where("id", $id)->delete();
        return $res ? json(["code" => 1, "msg" => "删除成功"]) : json(["code" => 0, "msg" => "删除失败"]);
    }

    public function edit_translation()
    {
        $id = input('id');
        $gift = Db::name('gifts')->find($id);
        $translations = Db::name('gifts_trans')->where('gift_id', $id)->select();
        if (empty($translations)) {
            $translations = [
                [
                    'id' => 0,
                    'gift_id' => $id,
                    'lang_code' => 'en',
                    'name' => '',
                ]
            ];
        }

        $img_domain = Configs::get('IMAGE_HOST');
        $this->assign(compact('gift', 'translations', 'img_domain'));
        return $this->fetch();
    }

    public function toggle_status()
    {
        $id = input('post.id/d');
        $status = input('post.status/d', 0);

        $gift = $this->model->where('id', $id)->find();
        if (!$gift) {
            return json(['code' => 0, 'msg' => '活动不存在']);
        }

        // 如果要开启，检查是否有时间重叠
        if ($status == 1 && $this->hasOverlap($gift)) {
            return json(['code' => 0, 'msg' => '存在时间重叠的活动，无法开启']);
        }

        $gift->status = $status;
        $gift->save();

        return json(['code' => 1, 'msg' => $status ? '已开启' : '已关闭']);
    }

    private function hasOverlap($gift): bool
    {
        $id = $gift['id'] ?? 0;

        return $this->model
            ->where('id', '<>', $id)
            ->where('pro_id', $gift['pro_id'])
            ->where('status', 1)
            ->where('start_time', '<=', $gift['end_time'])
            ->where('end_time', '>=', $gift['start_time'])
            ->count() > 0;
    }



    // 编辑子表（gift_items）
    public function items()
    {
        $gift_id = input('gift_id/d');
        $items = Db::name('gift_items')->where('gift_id', $gift_id)->select();
        $this->assign('gift_id', $gift_id);
        $this->assign('items', $items);
        return $this->fetch('items');
    }

    public function item_form()
    {
        $id = input('id/d');
        $gift_id = input('gift_id/d');
        $info = [];

        if ($id) {
            $info = Db::name('gift_items')->where('id', $id)->find();
        }

        // 获取该 gift_id 已存在的类型，确保是数组
        $usedTypes = Db::name('gift_items')->where('gift_id', $gift_id)->column('pro_type');
        if (!$usedTypes) {
            $usedTypes = [];  // 避免 null
        }

        $this->assign('info', $info);
        $this->assign('gift_id', $gift_id);
        $this->assign('usedTypes', $usedTypes);

        return $this->fetch('item_form');
    }

    public function save_item()
    {
        $data = input('post.');

        // 校验 gift_id + pro_type 唯一性
        $map = [
            ['gift_id', '=', $data['gift_id']],
            ['pro_type', '=', $data['pro_type']]
        ];
        if (!empty($data['id'])) {
            $map[] = ['id', '<>', $data['id']];
        }

        $exists = Db::name('gift_items')->where($map)->find();
        if ($exists) {
            return json(['code' => 0, 'msg' => '该类型已存在，请勿重复添加']);
        }

        if (empty($data['id'])) {
            Db::name('gift_items')->insert($data);
        } else {
            Db::name('gift_items')->where('id', $data['id'])->update($data);
        }
        return json(['code' => 1, 'msg' => '保存成功']);
    }

    public function del_item()
    {
        $id = input('id/d');
        Db::name('gift_items')->where('id', $id)->delete();
        return json(['code' => 1, 'msg' => '删除成功']);
    }

    public function clear()
    {
        $redis = new Redis();
        $keys = $redis->keys('gift_promotion*');
        if ($keys) {
            $redis->del($keys);
        }
        $this->success('清除赠品缓存成功!');
    }
}
