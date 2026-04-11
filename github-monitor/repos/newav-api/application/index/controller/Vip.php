<?php

namespace app\index\controller;
use app\extra\Rsa;
use app\index\model\Configs;
use app\index\model\Token;
use app\index\model\Vip as VipModel;
use app\index\model\Coin;
use app\index\model\Point;
use app\index\model\Platforms;
use app\index\model\VipOrder;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;

class Vip extends Base
{
    public $platform1 = 'Guozhi';
    public $platform2 = 'Wxr';

    /**
     * Notes:支付渠道
     *
     * DateTime: 2024/7/9 下午4:29
     */
    public function platforms(){
        $p_type = getInput('p_type') ?? 1;
        $vid    = getInput('vid');
        
        switch ($p_type){
            case 1:
                $v_info = VipModel::info($vid);
                break;
            case 2:
                $v_info = Point::info($vid);
                break;
            case 3:
                $v_info = Coin::info($vid);
                break;
        }
        if(!$v_info){
            return show(4001);
        }
        $amount = $v_info['is_sale'] ? $v_info['money'] : $v_info['cost'];
        $where  = "amounts = '-' or find_in_set(".(int)$amount.",amounts)";
        $platforms = Platforms::where('status','=',1)->where($where)->field('id,name,name_en,name_ru,type')->order('type asc,sort desc')->select()->toArray();
        if(empty($platforms)){
            return show(4002);
        }
        return show(1,$platforms);
    }

    /**
     * Notes:VIP列表
     *
     * DateTime: 2024/7/9 下午4:29
     */
    public function lists(){
        $p_type = getInput('p_type') ?? 1;
        switch ($p_type){
            case 1:
                $lists = VipModel::lists();
                break;
            case 2:
                $lists = Point::lists();
                break;
            case 3:
                $lists = Coin::lists();
                break;
        }
        return show(1,$lists);
    }

    /**
     * Notes:购买VIP
     *
     * DateTime: 2024/7/9 下午4:29
     */
    public function buy(){
        $uid        = Token::getCurrentUid();
        $orderModel = new VipOrder();
        $p_type     = getInput('p_type') ?? 1;
        $vid        = getInput('vid');
        $pid        = getInput('pid');

        switch ($p_type){
            case 1:
                $p_info = VipModel::where('id', $vid)->find();
                // $p_info = VipModel::info($vid);
                break;
            case 2:
                $p_info = Point::where('id', $vid)->find();
                // $p_info = Point::info($vid);
                break;
            case 3:
                $p_info = Coin::where('id', $vid)->find();
                // $p_info = Coin::info($vid);
                break;
        }
        if (!$p_info) {
            return show(4002);
        }

        $amount  = $p_info['is_sale'] == 1 ? $p_info['money'] : $p_info['cost'];
        $points  = $p_info['points'] ?? 0;
        $diamond = $p_info['diamonds'] ?? 0;
        $day     = $p_info['day'] ?? ($p_type == 1 ? $p_info['day'] : 0);

        //检查冷却
        $redis_key = 'buyVip_'.$uid;
        $redis     = new Redis();
        $cooldown  = $redis->get($redis_key);
        if($cooldown) return show(4004);

        $platformInfo = Platforms::field('id,key_name,type,pay_url,pay_code,val1')->where('status','=',1)->where('id','=',$pid)->find();
        if (!$platformInfo) {
            return show(4005);
        }

        $p1 = getRandChar(2,'0123456789');
        $p2 = getRandChar(3,'0123456789');
        $order_sn = $platformInfo['id'].$p1.time().$p2;
        $returnUrl = '';

        $num = mt_rand(1,1000);
        $kouliang = (int)Configs::where('id','=','6')->value('value');
        if($num > $kouliang){
            $is_kl = 0;
        }else{
            $is_kl = 1;
        }

        $ip = get_client_ip();
        switch ($platformInfo['key_name']) {
            case $this->platform1:
                $returnUrl = $this->getGuozhiUrl($amount,$order_sn,$platformInfo,$is_kl,$uid,$ip);
                break;
            case $this->platform2:
                $returnUrl = $this->getWxrUrl($amount,$order_sn,$platformInfo,$is_kl,$uid,$ip);
                break;
        }

        if(empty($returnUrl)){
            return show(4003);
        }

        $bounded = $redis->get('order_bounded_'.$uid);
        $order = [
            'uid'          => $uid,
            'product_id'   => $p_info['id'],
            'product_type' => $p_type,
            'diamond'      => $diamond,
            'point'        => $points,
            'pid'          => $pid,
            'title'        => $p_info->getData('title'), // the reason why use getData is lead the title wont effect by lang
            'title_en'     => $p_info['title_en'],
            'title_ru'     => $p_info['title_ru'],
            'title_ms'     => $p_info['title_ms'],
            'title_th'     => $p_info['title_th'],
            'title_es'     => $p_info['title_es'],
            'day'          => $day,
            'money'        => $amount,
            'add_time'     => time(),
            'order_sn'     => $order_sn,
            'pay_type'     => $platformInfo['type'],
            'is_kl'        => $is_kl,
            'bounded'      => $bounded,
            'ip'           => $ip
        ];
        $add_id = $orderModel::insert($order);
        
        if (!$add_id) {
            return show(4006);
        }
        //返回参数
        $returnData = [];
        $returnData['pay_url'] = $returnUrl;
        //设置冷却时间
        
        $redis->set($redis_key, 1, 30);
        return show(1,$returnData);
    }


    /**
     * Notes:果汁支付
     *
     * DateTime: 2024/7/9 下午4:29
     */
    private function getGuozhiUrl($amount, $order_sn,$platformInfo,$is_kl,$uid,$ip)
    {
        $url = $platformInfo['pay_url'];
        if($is_kl == 0){
            $gzConfig = [
                'merchant_id' => 'B20',
                'app_id'      => 'new_video',
                'key_file'    => '../public/key/key_new_video.txt',
            ];
        }else if($is_kl == 1){
            $gzConfig = [
                'merchant_id' => 'B20',
                'app_id'      => 'new_video',
                'key_file'    => '../public/key/key_new_video.txt',
            ];
        }
        $data = [
            'amount'      => $amount,
            'app_id'      => $gzConfig['app_id'],
            'merchant_id' => $gzConfig['merchant_id'],
            'order_no'    => $order_sn,
            'pay_type'    => $platformInfo['pay_code'],
            'currency'    => 'rmb',
            'timestamp'   => time(),
            'ip_address'  => $ip,
        ];
        $string = get_string($data);
        $key_file = $gzConfig['key_file'];
        //Sign data
        try
        {
            $fn = fopen($key_file, 'r');
            $key_detail = fread($fn, filesize($key_file));
            fclose($fn);
            $rsa  = new Rsa();
            $sign = $rsa->encrypt($string, $key_detail);
        }
        catch(\Exception $e)
        {
            return "";
        }
        $data['encode_sign'] = $sign;
        $result              = httpPost($url,$data);
        $arr                 = json_decode($result,JSON_UNESCAPED_UNICODE);

        if($arr['code'] != 0){
            save_log($arr, "makepaymentgz_error_".$is_kl);
            return "";
        }
        $redis_key = 'order_bounded_'.$uid;
        $redis     = new Redis();
        $redis->set($redis_key, $arr['data']['bounded'], 7200); //2小时
        return $arr['data']['url'];
    }


    /**
     * Notes:外星人支付
     *
     * DateTime: 2024/7/9 下午4:29
     */
    private function getWxrUrl($amount, $order_sn,$platformInfo,$is_kl,$uid,$ip)
    {
        $apiUrl     = Configs::get('api_url');
        $url        = $platformInfo['pay_url'];
        $notifyPath = 'callbackwxr';
        $returnPath = 'successwxr';

        if($is_kl == 0){
            $wxrConfig = [
                'partnerId' => 'D10018U',
                'project'   => 'new_video_1',
                'Md5key'    => 'W68DE6OZOO372R'
            ];
        }else if($is_kl == 1){
            $wxrConfig = [
                'partnerId' => 'D10018U',
                'project'   => 'new_video_1',
                'Md5key'    => 'W68DE6OZOO372R'
            ];
        }

        $postData = [
            "partnerId" => $wxrConfig['partnerId'],
            "orderId"   => $order_sn,
            "amount"    => $amount,
            "ip"        => $ip,
            "notifyUrl" => $apiUrl.'notify/'.$notifyPath,//服务器异步通知页面路径
            "returnUrl" => $apiUrl.'notify/'.$returnPath,//页面跳转同步通知页面路径
            "returnUrl" => "https://newavweb.9xyrp3kg4b86.com/purchase-history",
            "payType"   => $platformInfo['pay_code'],
            "project"   => $wxrConfig['project'],
        ];
        
        $Md5key = $wxrConfig['Md5key'];
        ksort($postData);                               //ASCII码排序
        reset($postData);                               //定位到第一个下标
        $md5str = "";
        foreach ($postData as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $md5str = substr($md5str, 0, -1);
        $sign   = strtoupper(md5($md5str.$Md5key));
        $postData["sign"] = $sign;

        $result  = httpPost($url,$postData);
        $result = json_decode($result,JSON_UNESCAPED_UNICODE);
        if($result['code'] == "0"){
            $returnURL = $result['data']['payUrl'];
            $redis_key = 'order_bounded_'.$uid;
            $redis = new Redis();
            $redis->set($redis_key, $result['data']['bounded'], 60); //2小时
            return $returnURL;
        }
        else{
            save_log($result, "makepaymentwxr_error_".$is_kl);
            return "";
        }
    }


    /**
     * Notes:我得订单
     *
     * DateTime: 2024/1/19 16:57
     */
    public function myOrder(){
        $uid    = Token::getCurrentUid();
        $page   = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):10;
        $lists  = VipOrder::lists($uid,$page,$limit);
        return show(1,$lists);
    }
}