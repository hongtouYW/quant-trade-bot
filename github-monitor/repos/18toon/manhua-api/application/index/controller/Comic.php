<?php

namespace app\index\controller;

use app\index\model\Chapter;
use app\index\model\History;
use app\index\model\Manhua;
use app\index\model\HomepageModules;
use app\index\model\Subscribe;
use app\index\model\Favorites;
use app\index\model\Record;
use app\index\model\Token;
use app\index\model\Manhua as ManhuaModel;
use app\lib\exception\BaseException;
use app\service\ElasticsearchService;
use app\traits\HasTranslation;

class Comic extends Base
{

    /**
     * Notes:推荐和vip
     * Type 1推荐 2VIP
     * DateTime: 2023/10/16 17:35
     */
    public function indexLists()
    {
        // payment_log("Wxr订单发起 -  ", "epay/request");
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $type = !empty(getInput('type')) ? (int) getInput('type') : 1;
        $date = !empty(getInput('date')) ? getInput('date') : '';
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::indexLists($page, $limit, $type, $date, $lang);
        return show(1, $lists);
    }

    /**
     * Notes:排行
     * Type 1畅销 2人气 3完结 4搜索
     * DateTime: 2023/10/16 17:35
     */
    public function rank()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $type = !empty(getInput('type')) ? (int) getInput('type') : 1;
        $date = getInput('date') != 0 ? getInput('date') : '';
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::rank($page, $limit, $type, $date, $lang);
        return show(1, $lists);
    }

    public function allRank()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $range = !empty(getInput('range')) ? getInput('range') : '';
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';

        // 解析时间范围
        $date = '';
        switch ($range) {
            case 'day': // 今日
                $date = date('Y-m-d');
                break;
            case 'week': // 最近7天
                $date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month': // 最近30天
                $date = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'all': // 历史排行榜，不限时间
            default:
                $date = ''; // 不传 update_time 条件
                break;
        }

        // 4种排行榜
        $types = [
            1 => 'hot',        // 畅销热卖
            2 => 'popular',    // 人气榜
            3 => 'finished',   // 完结榜
            4 => 'subscribe',  // 订阅榜
        ];

        $allRanks = [];
        foreach ($types as $type => $key) {
            $allRanks[$key] = Manhua::rank($page, $limit, $type, $date, $lang);
        }

        return show(1, $allRanks);
    }

    /**
     * Notes:题材
     *
     * DateTime: 2023/10/16 17:38
     */
    public function ticai()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::ticai($lang);
        return show(1, $lists);
    }

    /**
     * Notes:标签
     *
     * DateTime: 2023/10/16 17:38
     */
    public function tags()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = \app\index\model\Tags::lists($lang);
        return show(1, $lists);
    }




    /**
     * Notes:书库
     * Order 1最热 2最新 3口碑
     * DateTime: 2023/10/16 17:36
     */
    public function lists()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $type = !empty(getInput('type')) ? (int) getInput('type') : 1;
        $ticai_id = getInput('ticai_id') != 0 ? (int) getInput('ticai_id') : '';
        $tag = getInput('tag') != 0 ? (int) getInput('tag') : '';
        $keyword = !empty(getInput('keyword')) ? getInput('keyword') : '';
        $mhstatus = in_array(getInput('mhstatus'), ['0', '1'], true) ? getInput('mhstatus') : '';
        $xianmian = in_array(getInput('xianmian'), ['0', '1'], true) ? getInput('xianmian') : '';
        $year = getInput('year') != 0 ? getInput('year') : '';
        $month = getInput('month') != 0 ? getInput('month') : '';
        $weekday = getInput('weekday') != 0 ? getInput('weekday') : '';
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::lists($page, $limit, $ticai_id, $tag, $keyword, $mhstatus, $xianmian, $year, $month, $weekday, $type, $lang);
        return show(1, $lists);
    }

    public function search()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $ticai_id = getInput('ticai_id') != 0 ? (int) getInput('ticai_id') : '';
        $tag = getInput('tag') != 0 ? (int) getInput('tag') : '';
        $keyword = !empty(getInput('keyword')) ? getInput('keyword') : '';
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::lists($page, $limit, $ticai_id, $tag, $keyword, '', '', '', '', '', 1, $lang);
        return show(1, $lists);
    }

    public function searchEs()
    {
        $keyword = !empty(getInput('keyword')) ? getInput('keyword') : '';
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 10;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';

        // 获取索引名
        $indexName = ElasticsearchService::getIndexName($lang);
        $es = ElasticsearchService::client();

        $result = $es->search([
            'index' => $indexName,
            'body' => [
                'size' => $limit,
                'query' => [
                    'multi_match' => [
                        'query' => $keyword,
                        'fields' => ['title', 'desc']
                    ]
                ]
            ]
        ]);

        $hits = $result['hits']['hits'];
        $ids = array_column($hits, '_id');

        $query = Manhua::alias('m')
            ->field(Manhua::list_tran_field)
            ->whereIn('m.id', $ids);

        // 处理漫画和翻译
        HasTranslation::withTranslation($query, 'm', $lang, ['title', 'desc', 'keyword', 'last_chapter_title', 'image', 'cover_horizontal'], 'qiswl_manhua_trans', 'manhua_id');

        $list = $query->select();

        return show(1, $list);
    }

    /**
     * Notes:漫画详情
     *
     * DateTime: 2023/10/21 15:01
     */
    public function info()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 10;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $sort = !empty(getInput('sort')) ? getInput('sort') : 'asc';

        $mid = Manhua::getMid();
        $info = Manhua::info($mid, $page, $limit, $lang, $sort);
        return show(1, $info);
    }

    /**
     * Notes:获取章节列表
     *
     * DateTime: 2023/10/21 15:28
     */
    public function chapterList()
    {
        $mid = Manhua::getMid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 10;
        $order = !empty(getInput('order')) ? (int) getInput('order') : 1;
        $lists = Chapter::chapterList($mid, $page, $limit, $order);
        return show(1, $lists);
    }


    /**
     * Notes: 检测漫画章节是否解锁
     *
     * DateTime: 2025/04/23
     */
    public function chapterAccessInfo()
    {
        $uid = getUid();
        $mid = getInput('mid');

        if (empty($mid)) {
            return show(0, 'mid参数不能为空');
        }

        $lists = Record::listsByMidWithBuyStatus($uid, $mid);
        return show(1, $lists);
    }

    /**
     * Notes:获取章节信息
     *
     * DateTime: 2023/10/21 15:28
     */
    public function chapterInfo()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $checkSwitch = !empty(getInput('check_switch')) ? true : false;
        $chaInfo = Chapter::getChaInfo($checkSwitch);
        $lists = Chapter::info($chaInfo['id'], $lang);
        // $uid = getUid();
        // if ($uid) {
        //     History::add_log($uid, $chaInfo['manhua_id'], $chaInfo['id']);
        // }
        return show(1, $lists);
    }

    public function checkChaptersStatus()
    {
        $uid = getUid();
        $mid = getInput('mid');

        if (!$uid || !$mid) {
            return show(0, '参数缺失');
        }

        $manhua = Manhua::where('id', $mid)
            ->field(Manhua::info_field)
            ->find();

        if (!$manhua) {
            return show(0, '漫画不存在');
        }

        $purchasedChapterIds = Record::where([
            'member_id' => $uid,
            'manhua_id' => $mid,
        ])->order('capter_id', 'asc')
            ->column('capter_id');

        $viewedChapterIds = History::where([
            'member_id' => $uid,
            'manhua_id' => $mid,
        ])->group('capter_id')
            ->order('capter_id', 'asc')
            ->column('capter_id');

        $data = [
            'manhua' => $manhua->append(['last_view', 'is_subscribe', 'is_favorite']),
            'purchased_chapter_ids' => $purchasedChapterIds ?: [],
            'viewed_chapter_ids' => $viewedChapterIds ?: [],
        ];

        return show(1, $data);
    }

    /**
     * Notes:单独购买章节
     *
     * DateTime: 2023/11/24 14:32
     */
    public function chapterBuy()
    {
        $chaInfo = Chapter::getChaInfo();
        $uid = Token::getCurrentUid();
        $result = Chapter::buy($chaInfo['id'], $uid);
        if ($uid) {
            History::add_log($uid, $chaInfo['manhua_id'], $chaInfo['id']);
        }
        return show(1, $result);
    }

    /**
     * Notes:收藏
     *
     * DateTime: 2022/4/26 23:54
     */
    public function subscribe()
    {
        $uid = Token::getCurrentUid();
        $mid = Manhua::getMid();
        $model = new Subscribe();

        $rateKey = 'subscribe_rate_limit_' . $uid;

        if (rateLimit($rateKey, 5)) {
            return show(1010);
        }

        $exists = $model
            ->where('member_id', $uid)
            ->where('manhua_id', $mid)
            ->find();

        if ($exists) {
            if ($exists['status'] == 1) {
                return show(0, '已订阅');
            } else {
                $update = $model
                    ->where('id', $exists['id'])
                    ->update(['status' => 1]);

                if ($update !== false) {
                    Manhua::where('id', '=', $mid)->setInc('subscribe');
                    Manhua::where('id', '=', $mid)->setInc('monthly_subscribe');
                    return show(1, 1);
                } else {
                    return show(0, '恢复订阅失败');
                }
            }
        }

        $result = $model->insert([
            'member_id' => $uid,
            'manhua_id' => $mid,
            'status' => 1,
            'addtime' => time()
        ]);

        if (!$result) {
            return show(0, '订阅失败');
        }

        Manhua::where('id', '=', $mid)->setInc('subscribe');
        Manhua::where('id', '=', $mid)->setInc('monthly_subscribe');
        return show(1, 1);
    }


    /**
     * Notes: 取消订阅  
     *
     * DateTime: 2025/04/11 14：00
     */
    public function unsubscribe()
    {
        $uid = Token::getCurrentUid();
        $ids = getInput('mid');

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        if (is_numeric($ids)) {
            $ids = [(int) $ids];
        }

        if (empty($ids) || !is_array($ids)) {
            return show(0, '漫画ID必须为数组或整数');
        }

        $rateKey = 'unsubscribe_rate_limit_' . $uid;

        if (rateLimit($rateKey, 5)) {
            return show(1010);
        }

        $model = new Subscribe();
        $result = $model
            ->where('member_id', $uid)
            ->whereIn('manhua_id', $ids)
            ->update(['status' => 0]);

        if (!$result) {
            return show(0, '取消订阅失败');
        }

        return show(1, 1);
    }


    /**
     * Notes: 收藏漫画
     * DateTime: 2025/04/11 15:30
     */
    public function Favorite()
    {
        $uid = Token::getCurrentUid();
        $mid = Manhua::getMid();

        if (empty($mid)) {
            return show(3001, '漫画ID不能为空');
        }

        $rateKey = 'favorite_rate_limit_' . $uid;

        if (rateLimit($rateKey, 5)) {
            return show(1010);
        }

        $model = new Favorites();
        $exists = $model
            ->where('member_id', $uid)
            ->where('manhua_id', $mid)
            ->find();

        if ($exists) {
            if ($exists['status'] == 1) {
                return show(0, '该漫画已收藏');
            } else {
                $update = $model
                    ->where('id', $exists['id'])
                    ->update(['status' => 1]);

                if ($update !== false) {
                    return show(1, '收藏成功');
                } else {
                    return show(0, '恢复收藏失败');
                }
            }
        }

        $result = $model->insert([
            'member_id' => $uid,
            'manhua_id' => $mid,
            'status' => 1,
            'addtime' => time()
        ]);

        if (!$result) {
            return show(0, '收藏失败');
        }

        return show(1, 1);
    }


    /**
     * Notes: 取消收藏
     * DateTime: 2025/04/11 15:32
     */
    public function unfavorite()
    {
        $uid = Token::getCurrentUid();
        $ids = getInput('mid');

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        if (is_numeric($ids)) {
            $ids = [(int) $ids];
        }

        if (empty($ids) || !is_array($ids)) {
            return show(3001, '漫画ID必须为数组或整数');
        }

        $rateKey = 'unfavorite_rate_limit_' . $uid;

        if (rateLimit($rateKey, 5)) {
            return show(1010);
        }

        $model = new Favorites();
        $result = $model
            ->where('member_id', $uid)
            ->whereIn('manhua_id', $ids)
            ->update(['status' => 0]);

        if (!$result) {
            return show(0, '取消收藏失败');
        }

        return show(1, 1);
    }

    /**
     * Notes: 猜你喜欢
     * DateTime: 2025/05/08 00:00
     */
    public function guess()
    {
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $mid = !empty(getInput('mid')) ? (int) getInput('mid') : null;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::guess($limit, $lang, $mid);

        return show(1, $lists);
    }

    /**
     * Notes: 首页自定义结构
     * DateTime: 2025/05/08 00:00
     */
    public function homepage()
    {
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'en';

        $query = HomepageModules::alias('m')
            ->field('m.*')
            ->where('m.status', 1)
            ->order('m.sort', 'asc');

        HasTranslation::withTranslation($query, 'm', $lang, ['name'], 'qiswl_homepage_modules_trans', 'module_id');

        $modulesPage = $query->paginate([
            'page' => $page,
            'list_rows' => $limit,
        ])->toArray();

        $modules = $modulesPage['data'];

        $data = [];

        foreach ($modules as $mod) {
            $method = $mod['module'];
            $params = json_decode($mod['params'], true) ?? [];
            $params['lang'] = $lang;

            switch ($method) {
                case 'indexLists':
                    $result = Manhua::indexLists(
                        $params['page'] ?? 1,
                        $params['limit'] ?? 12,
                        $params['type'] ?? 1,
                        $params['date'] ?? '',
                        $params['lang']
                    );
                    break;

                case 'rank':
                    $result = Manhua::rank(
                        $params['page'] ?? 1,
                        $params['limit'] ?? 12,
                        $params['type'] ?? 1,
                        $params['date'] ?? '',
                        $params['lang']
                    );
                    break;

                case 'allRank':
                    $range = $params['range'] ?? '';
                    $date = '';
                    switch ($range) {
                        case 'day':
                            $date = date('Y-m-d');
                            break;
                        case 'week':
                            $date = date('Y-m-d', strtotime('-7 days'));
                            break;
                        case 'month':
                            $date = date('Y-m-d', strtotime('-30 days'));
                            break;
                        case 'all':
                        default:
                            $date = '';
                    }

                    $types = [
                        1 => 'hot',
                        2 => 'popular',
                        3 => 'finished',
                        4 => 'subscribe',
                    ];

                    $result = [];
                    foreach ($types as $type => $key) {
                        $result[$key] = Manhua::rank(
                            $params['page'] ?? 1,
                            $params['limit'] ?? 12,
                            $type,
                            $date,
                            $params['lang']
                        );
                    }
                    break;

                case 'lists':
                    $result = Manhua::lists(
                        $params['page'] ?? 1,
                        $params['limit'] ?? 12,
                        $params['ticai_id'] ?? '',
                        $params['tag'] ?? '',
                        $params['keyword'] ?? '',
                        $params['mhstatus'] ?? '',
                        $params['xianmian'] ?? '',
                        $params['year'] ?? '',
                        $params['month'] ?? '',
                        $params['weekday'] ?? '',
                        $params['type'] ?? 1,
                        $params['lang']
                    );
                    break;

                default:
                    $result = [];
                    break;
            }

            $data[] = [
                'id' => $mod['id'],
                'module' => $method,
                'name' => $mod['name'],
                'is_highlight' => $mod['is_highlight'],
                'params' => $params,
                'data' => $result
            ];
        }

        return show(1, [
            'total' => $modulesPage['total'],
            'per_page' => $modulesPage['per_page'],
            'current_page' => $modulesPage['current_page'],
            'last_page' => $modulesPage['last_page'],
            'data' => $data
        ]);
    }

}
