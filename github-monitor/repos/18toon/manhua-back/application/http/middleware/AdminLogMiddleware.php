<?php

namespace app\http\middleware;

use app\index\model\SystemLog;
use think\Request;
use think\Db;

class AdminLogMiddleware
{
    public function handle($request, \Closure $next)
    {
        // 继续执行请求
        $response = $next($request);

        // 判断是否是后台操作（你可以改为更精确的判断）
        if (strtolower($request->module()) === 'index') {

            // 只记录非GET请求
            if (!in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
                return $response;
            }

            // 获取 session 中的管理员ID（依据你的登录逻辑）
            $adminId = session('admin_id') ?: 0;
            if (!$adminId) return $response;

            $systemLog = new SystemLog();

            // 获取请求信息
            $route = $request->url(true);
            $method = $request->method();
            $params = $request->param();
            $ip = $request->ip();
            $address = $systemLog->getAddress($ip);

            // 写入日志
            Db::table('system_log')->insert([
                'title' => '后台操作 - ' . $method,
                'admin_id' => $adminId,
                'user_id' => 0,
                'routes' => $route,
                'content' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'address' => $address,
                'status' => 1,
                'created_at' => time()
            ]);
        }

        return $response;
    }
}
