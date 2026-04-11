<?php

namespace app\index\controller;

use think\Db;
use think\Controller;

class Notify extends Controller
{
    public function count()
    {
        $data = [
            'tasks' => Db::name('manhua')->where('status', 0)->where('audit_user_id', session('admin_id'))->count(),
        ];
        return json(['code' => 1, 'data' => $data]);
    }
}
