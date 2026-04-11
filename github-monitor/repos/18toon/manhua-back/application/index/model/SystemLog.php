<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/1/17
 * Time: 11:12
 */

namespace app\index\model;

class SystemLog extends BaseModel
{
    protected $table="system_log";

    protected $autoWriteTimestamp = true;
    // 定义时间戳字段名
    protected $createTime = 'created_at';
    public function getAdminNameAttr($value,$data){
        return model('admin')->where('id','=',$data['admin_id'])->value('username');
    }

    CONST STATUS = [
        1 => '正常',
        2 => '异常'
    ];

    /*
     *根据用户id获取用户名
     */
    public function getUsernameAttr($value,$data){

        return model('user')->where('id','=',$data['user_id'])->value('username');
    }


    /*
     *根据状态码返回
     */
    public function getStatusTextAttr($value,$data){

        return self::STATUS[$data['status']];
    }

    /*
     * 插入日志
     */
    public function insertLog($data){
        $data['routes'] = $this->getRoute();
        $data['ip'] = $this->getIp();
        $data['address'] = $this->getAddress($data['ip']);
        $this->save($data);
    }

    /*
     * 获取ip
     */
    private function getIp()
    {
        return request()->ip();
    }

    /*
     * 根据ip获取地址
     */
    public function getAddress($ip)
    {
        $address = '';
        $ipArr[] = $ip;
        $url = 'http://ip-api.com/batch?lang=zh-CN&fields=country,regionName,city';
        $options = [
            'http' => [
                'method' => 'POST',
                'user_agent' => 'Batch-Example/1.0',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($ipArr)
            ]
        ];
        $response = file_get_contents($url, false, stream_context_create($options));
        $result = json_decode($response, true)[0];
        if($result){
            $address = $result['country'] .'--'.$result['regionName'].'--'.$result['city'];
        }
        return $address;
    }

    /**
     * 获取路由
     */
    private function getRoute()
    {
        $module = \think\facade\Request::instance()->module();
        $controller = \think\facade\Request::instance()->controller();
        $action = \think\facade\Request::instance()->action();
        return $module.'/'.$controller.'/'.$action;
    }

}