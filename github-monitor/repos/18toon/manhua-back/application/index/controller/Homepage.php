<?php
namespace app\index\controller;

use think\Db;
use app\index\model\HomepageModules;

class Homepage extends Base
{
    protected $model;

    public function initialize()
    {
        $this->model = new HomepageModules();
    }

    // 列表页
    public function index()
    {
        $param = input();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 20;

        $list = $this->model->page($page, $limit)->order('sort asc')->select();
        $total = $this->model->count();

        $moduleParams = include ROOT_PATH . 'config/module_params.php';

        foreach ($list as &$vo) {
            $vo['module_label'] = $moduleParams[$vo['module']]['label'] ?? $vo['module'];
        }

        foreach ($list as &$vo) {
            $vo['params'] = $this->formatParamsWithLabel($vo['module'], $vo['params']);
        }

        $this->assign([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);

        $param['page'] = '{page}';
        $param['limit'] = '{limit}';
        $this->assign('param', $param);

        return $this->fetch();
    }

    // 新增/编辑页
    public function info()
    {
        if (request()->isPost()) {
            $param = request()->post();

            // -------------------- 获取模块配置 --------------------
            $moduleParams = include ROOT_PATH . 'config/module_params.php';
            $moduleKey = $param['module'] ?? ''; // 当前模块 key
            $allowedKeys = [];

            if ($moduleKey && isset($moduleParams[$moduleKey]['params'])) {
                $allowedKeys = array_column($moduleParams[$moduleKey]['params'], 'key');
            }

            // -------------------- 补全默认参数 --------------------
            if (isset($param['params']) && is_array($param['params'])) {
                $defaultParams = [
                    'page' => 1,
                    'limit' => 12,
                    'type' => 1,
                    'date' => 0,        // rank
                    'range' => 'all',   // allRank
                    'ticai_id' => 0,    // lists
                    'tag' => '0',        // lists
                    'mhstatus' => 2,    // lists
                    'xianmian' => 2,    // lists
                    'year' => 0,        // lists
                    'month' => 0,       // lists
                    'weekday' => 0,     // lists
                ];

                foreach ($allowedKeys as $k) {
                    if (!isset($param['params'][$k]) || $param['params'][$k] === '') {
                        if (isset($defaultParams[$k])) {
                            $param['params'][$k] = $defaultParams[$k];
                        }
                    }
                }
            }

            // -------------------- params → JSON --------------------
            if (!isset($param['params']) || empty($param['params'])) {
                $param['params'] = '{}';
            } elseif (is_array($param['params'])) {
                $param['params'] = json_encode($param['params'], JSON_UNESCAPED_UNICODE);
            } elseif (is_string($param['params'])) {
                json_decode($param['params'], true) === null && $param['params'] = '{}';
            }

            // -------------------- 保存主数据 --------------------
            $res = $this->model->saveData($param);
            if ($res['code'] > 1) {
                return $this->error($res['msg']);
            }

            $moduleId = $res['id'];

            // -------------------- 保存翻译 --------------------
            if (!empty($param['translations']) && is_array($param['translations'])) {
                foreach ($param['translations'] as $lang => $trans) {
                    if ($lang === 'zh') {
                        $data = [
                            'id' => $moduleId,
                            'name' => $trans['name'] ?? '',
                        ];
                        Db::name('homepage_modules')->update($data);
                        continue;
                    }

                    $transData = [
                        'module_id' => $moduleId,
                        'lang_code' => $lang,
                        'name' => $trans['name'] ?? '',
                    ];

                    insertOrUpdate(
                        'qiswl_homepage_modules_trans',
                        ['module_id' => $moduleId, 'lang_code' => $lang],
                        $transData
                    );
                }
            }

            return $this->success($res['msg']);
        }

        // ================= GET =================
        $id = input('id/d', 0);

        $info = Db::name('homepage_modules')->where('id', $id)->find();

        // ================== 🔥 补 params 默认值 ==================
        if (empty($info['params'])) {
            $info['params'] = '{}';
        }

        if ($id == 0) {
            $maxSort = Db::name('homepage_modules')->max('sort');
            $info['sort'] = ($maxSort ?? 0) + 1;
        }

        $translations = Db::name('homepage_modules_trans')
            ->where('module_id', $id)
            ->select();

        // zh 主语言
        $zh = [
            'lang_code' => 'zh',
            'name' => $info['name'] ?? ''
        ];

        // 整理语言翻译
        $translationsByLang = ['zh' => $zh];

        foreach ($translations as $row) {
            $translationsByLang[$row['lang_code']] = [
                'lang_code' => $row['lang_code'],
                'name' => $row['name'] ?? ''
            ];
        }

        // 多语言列表
        $langList = [
            ['lang_code' => 'en'],
        ];

        // 自动补齐
        foreach ($langList as $l) {
            if (!isset($translationsByLang[$l['lang_code']])) {
                $translationsByLang[$l['lang_code']] = [
                    'lang_code' => $l['lang_code'],
                    'name' => ''
                ];
            }
        }

        $moduleParams = include ROOT_PATH . 'config/module_params.php';

        $this->assign([
            'info' => $info,
            'moduleParams' => $moduleParams,
            'ticaiList' => Db::name('ticai')->select(),
            'tagList' => Db::table('tags')->select(),
            'translations' => $translationsByLang,
            'langList' => $langList
        ]);

        return $this->fetch();
    }

    // 删除
    public function del()
    {
        $id = input('id');

        Db::startTrans();
        try {
            $this->model->where('id', $id)->delete();

            Db::name('homepage_modules_trans')->where('module_id', $id)->delete();

            Db::commit();

            return json(["code" => 1, "msg" => "删除成功"]);
        } catch (\Exception $e) {
            Db::rollback();

            return json(["code" => 0, "msg" => "删除失败", "error" => $e->getMessage()]);
        }
    }

    public function tagSearch()
    {
        $keyword = input('get.keyword', '');
        $query = Db::table('tags');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $tags = $query->limit(50)->field('id,name')->select();

        $data = [];
        foreach ($tags as $t) {
            $data[] = ['name' => $t['name'], 'value' => $t['id']];
        }

        // xmSelect 要求返回 data 数组
        return json(['code' => 0, 'msg' => '', 'data' => $data]);
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


    public function clear()
    {
        $urls = [
            "/data/comic/homepage-1-20-zh.js",
            "/data/comic/homepage-2-20-zh.js",
            "/data/comic/homepage-1-20-en.js",
            "/data/comic/homepage-2-20-en.js",
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

    private function formatParamsWithLabel($moduleType, $paramsJson)
    {
        if (empty($paramsJson)) {
            return $paramsJson;
        }

        $params = json_decode($paramsJson, true);
        if (!is_array($params)) {
            return $paramsJson;
        }

        $moduleParams = include ROOT_PATH . 'config/module_params.php';
        if (!isset($moduleParams[$moduleType])) {
            return $paramsJson;
        }

        $paramConfigs = $moduleParams[$moduleType]['params'];

        // key → config 映射
        $configMap = [];
        foreach ($paramConfigs as $cfg) {
            $configMap[$cfg['key']] = $cfg;
        }

        $result = [];

        // 先按照配置顺序输出
        foreach ($paramConfigs as $cfg) {
            $key = $cfg['key'];
            if (!isset($params[$key])) {
                continue; // 配置了但未传值就跳过
            }

            $value = $params[$key];
            $label = $cfg['label'] ?? $key;
            $valueText = $value;

            // 如果配置了 options
            if (isset($cfg['options'])) {
                if (array_key_exists($value, $cfg['options'])) {
                    $valueText = $cfg['options'][$value];
                } elseif (in_array($value, $cfg['options'])) {
                    $valueText = $value;
                }
            }

            // 特殊处理 year / month
            if ($key === 'year' || $key === 'month') {
                $valueText = $value == 0 ? '全部' : $value;
            }

            $result[$label] = $valueText;
        }

        // 剩余未配置的参数，放到最后
        foreach ($params as $key => $value) {
            if (isset($configMap[$key]))
                continue;
            $result[$key] = $value;
        }

        // 输出美观多行文本
        $lines = [];
        foreach ($result as $k => $v) {
            $lines[] = "{$k}：{$v}";
        }

        return implode("\n", $lines);
    }

}
