<?php

return [
    'indexLists' => [
        'label' => '首页列表',
        'params' => [
            [
                'key' => 'type',
                'label' => '类型 type',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    1 => '最近更新',
                    3 => '精选推荐',
                    4 => '限免推荐'
                ]
            ],
            ['key' => 'page', 'label' => '分页 page', 'type' => 'number', 'placeholder' => '页码，例如：1'],
            ['key' => 'limit', 'label' => '数量 limit', 'type' => 'number', 'placeholder' => '数量，例如：20'],
        ]
    ],

    'rank' => [
        'label' => '排行榜',
        'params' => [
            ['key' => 'date', 'label' => '日期 date', 'type' => 'text', 'placeholder' => '20250101 或 0 代表全部'],
            [
                'key' => 'type',
                'label' => '排行榜类型 type',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    1 => '畅销热卖',
                    2 => '人气榜',
                    3 => '完结榜',
                    4 => '订阅榜'
                ]
            ],
            ['key' => 'page', 'label' => '分页 page', 'type' => 'number', 'placeholder' => '页码'],
            ['key' => 'limit', 'label' => '数量 limit', 'type' => 'number', 'placeholder' => '数量'],
        ]
    ],

    'allRank' => [
        'label' => '综合排行榜',
        'params' => [
            [
                'key' => 'range',
                'label' => '范围 range',
                'type' => 'select',
                'options' => [
                    'day' => '今日',
                    'week' => '本周',
                    'month' => '本月',
                    'all' => '全部'
                ],
                'placeholder' => '选择排行区间'
            ],
            ['key' => 'page', 'label' => '分页 page', 'type' => 'number', 'placeholder' => '页码'],
            ['key' => 'limit', 'label' => '条数 limit', 'type' => 'number', 'placeholder' => '数量'],
        ]
    ],

    'lists' => [
        'label' => '漫画列表',
        'params' => [
            [
                'key' => 'type',
                'label' => '类型 type',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    1 => '浏览排序',
                    2 => '最新排序',
                    3 => '订阅推荐',
                    4 => '销量推荐',
                ]
            ],
            ['key' => 'ticai_id', 'label' => '题材ID', 'type' => 'number', 'placeholder' => '0 = 全部'],
            ['key' => 'tag', 'label' => '标签 tag_id', 'type' => 'text'],
            [
                'key' => 'mhstatus',
                'label' => '连载状态',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    0 => '连载',
                    1 => '完结',
                    2 => '全部',
                ]
            ],
            [
                'key' => 'xianmian',
                'label' => '限免',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    0 => '否',
                    1 => '是',
                    2 => '全部',
                ]
            ],
            ['key' => 'year', 'label' => '年份', 'type' => 'number', 'placeholder' => '2025 或 0'],
            ['key' => 'month', 'label' => '月份', 'type' => 'number', 'placeholder' => '1~12 或 0'],
            [
                'key' => 'weekday',
                'label' => '星期',
                'type' => 'number',
                'as_radio' => true,
                'options' => [
                    1 => '周一',
                    2 => '周二',
                    3 => '周三',
                    4 => '周四',
                    5 => '周五',
                    6 => '周六',
                    7 => '周日',
                    0 => '全部'
                ]
            ],
            ['key' => 'page', 'label' => '页 page', 'type' => 'number', 'placeholder' => '页码'],
            ['key' => 'limit', 'label' => '条数 limit', 'type' => 'number', 'placeholder' => '数量'],
        ]
    ],
];
