<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

Route::group('user', function () {
    Route::post('create', 'user/create');
    Route::post('info', 'user/info');
    Route::post('bind_google', 'user/bind_google');
});

Route::group('currency', function () {
    Route::post('list', 'currency/list');
});

