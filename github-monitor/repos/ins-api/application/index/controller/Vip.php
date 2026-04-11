<?php

namespace app\index\controller;
use app\extra\Rsa;
use app\index\model\Configs;
use app\index\model\Token;
use app\index\model\Vip as VipModel;
use app\index\model\Platforms;
use app\index\model\Vip2;
use app\index\model\Vip3;
use app\index\model\Vip4;
use app\index\model\VipOrder;
use app\index\model\VipOrder2;
use app\index\model\VipOrder3;
use app\index\model\VipOrder4;
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


        switch ($this->site){
            case 1:
                $model = new VipModel();
                break;
            case 2:
                $model = new Vip2();
                break;
            case 3:
                $model = new Vip3();
                break;
            case 4:
                $model = new Vip4();
                break;
        }

        $vid = getInput('vid');
        $vipInfo =$model::where('id','=',$vid)->field('id,cost,money,is_sale')->find();
        if(!$vipInfo){
            return show(4001);
        }
        $amount = $vipInfo['money'];
        $where = "amounts = '-' or find_in_set(".(int)$amount.",amounts)";
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
        switch ($this->site){
            case 1:
                $model = new VipModel();
                break;
            case 2:
                $model = new Vip2();
                break;
            case 3:
                $model = new Vip3();
                break;
            case 4:
                $model = new Vip4();
                break;
        }
        $lists = $model::lists();
        return show(1,$lists);
    }

    /**
     * Notes:购买VIP
     *
     * DateTime: 2024/7/9 下午4:29
     */
    public function buy(){
        $uid = Token::getCurrentUid();

        switch ($this->site){
            case 1:
                $model = new VipModel();
                $orderModel = new VipOrder();
                break;
            case 2:
                $model = new Vip2();
                $orderModel = new VipOrder2();
                break;
            case 3:
                $model = new Vip3();
                $orderModel = new VipOrder3();
                break;
            case 4:
                $model = new Vip4();
                $orderModel = new VipOrder4();
                break;
        }

        //检查冷却
        $redis_key = 'buyVip_'.$this->site.'_'.$uid;
        $redis = new Redis();
        $cooldown = $redis->get($redis_key);
        if($cooldown) return show(4004);

        $vid = getInput('vid');
        $agent_code = getInput('agent_code');
        $vipInfo = $model::where('id','=',$vid)->field('id,title,day,money')->find();
        if (!$vipInfo) {
            return show(4002);
        }

        $pid = getInput('pid');
        $platformInfo = Platforms::field('id,key_name,type,pay_url,pay_code,val1')->where('status','=',1)->where('id','=',$pid)->find();
        if (!$platformInfo) {
            return show(4005);
        }

        $amount = $vipInfo['money'];

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
                $returnUrl = $this->getGuozhiUrl($amount,$order_sn,$platformInfo,$is_kl,$uid,$ip,$this->site);
                break;
            case $this->platform2:
                $returnUrl = $this->getWxrUrl($amount,$order_sn,$platformInfo,$is_kl,$uid,$ip,$this->site);
                break;
        }

        if(empty($returnUrl)){
            return show(4003);
        }


        $bounded = $redis->get('order_bounded_'.$uid);
        $order = [
            'uid' => $uid,
            'agent_code' => $agent_code,
            'vid'=>$vid,
            'pid'=>$pid,
            'title'=>$vipInfo['title'],
            'day'=>$vipInfo['day'],
            'money'=>$vipInfo['money'],
            'add_time' => time(),
            'is_kl'=>$is_kl,
            'order_sn'=>$order_sn,
            'pay_type' => $platformInfo['type'],
            'bounded' =>$bounded,
            'ip' =>$ip
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
    private function getGuozhiUrl($amount, $order_sn,$platformInfo,$is_kl,$uid,$ip,$site)
    {
        $url = $platformInfo['pay_url'];
        $custom_column = array(1 => '', 2 => 'wuma', 3 => 'dm', 4 => '4k')[$site] ?? '';

        if($is_kl == 0){
            $gzConfig = [
                'merchant_id'=>'A3',
                'app_id'=>'insapi',
                'key_file'=>'../public/key/key_3_insapi.txt',
            ];
        }else if($is_kl == 1){
            $gzConfig = [
                'merchant_id'=>'R4',
                'app_id'=>'insapi2',
                'key_file'=>'../public/key/key_4_insapi2.txt'
            ];
        }
        $data = [
            'amount'=>$amount,
            'app_id'=>$gzConfig['app_id'],
            'merchant_id'=>$gzConfig['merchant_id'],
            'order_no'=>$order_sn,
            'pay_type'=>$platformInfo['pay_code'],
            'currency'=>'rmb',
            'timestamp'=>time(),
            'custom_column'=>$custom_column,
            'ip_address'=>$ip,
        ];

        if($site == 1){
            unset($data['custom_column']);
        }

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
        $result  = httpPost($url,$data);
        $arr = json_decode($result,JSON_UNESCAPED_UNICODE);
        if($arr['code'] != 0){
            save_log($arr, "makepaymentgz_error_".$site.'_'.$is_kl);
            return "";
        }
        $redis_key = 'order_bounded_'.$uid;
        $redis = new Redis();
        $redis->set($redis_key, $arr['data']['bounded'], 60); //2小时
        return $arr['data']['url'];
    }


    /**
     * Notes:外星人支付
     *
     * DateTime: 2024/7/9 下午4:29
     */
    private function getWxrUrl($amount, $order_sn,$platformInfo,$is_kl,$uid,$ip,$site)
    {
        $apiUrl = Configs::get('api_url');
        $url = $platformInfo['pay_url'];

        if($is_kl == 0){
            $wxrConfig = [
                'partnerId'=>'M3P',
                'project'=>'insapi',
                'Md5key'=>'3K7QHFUTU80W5X'
            ];
        }else if($is_kl == 1){
            $wxrConfig = [
                'partnerId'=>'H4B',
                'project'=>'insapi2',
                'Md5key'=>'PO6YGWFIIJ0ZG1'
            ];
        }
        switch ($site){
            case 1:
                $notifyPath = 'callbackwxr';
                $returnPath = 'successwxr';
                break;
            case 2:
                $notifyPath = 'callbackwxrwuma';
                $returnPath = 'successwxrwuma';
                break;
            case 3:
                $notifyPath = 'callbackwxrdm';
                $returnPath = 'successwxrdm';
                break;
            case 4:
                $notifyPath = 'callbackwxr4k';
                $returnPath = 'successwxr4k';
                break;
        }


        $postData = [
            "partnerId" => $wxrConfig['partnerId'],
            "orderId" => $order_sn,
            "amount"=>$amount,
            "ip"=>$ip,
            "notifyUrl"  => $apiUrl.'notify/'.$notifyPath,//服务器异步通知页面路径
            "returnUrl"  => $apiUrl.'notify/'.$returnPath,//页面跳转同步通知页面路径
            "payType" => $platformInfo['pay_code'],
            "project" => $wxrConfig['project'],
        ];
        $Md5key = $wxrConfig['Md5key'];
        ksort($postData);                               //ASCII码排序
        reset($postData);                               //定位到第一个下标
        $md5str = "";
        foreach ($postData as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $md5str = substr($md5str, 0, -1);
        $sign = strtoupper(md5($md5str.$Md5key));
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
            save_log($result, "makepaymentwxr_error_".$site.'_'.$is_kl);
            return "";
        }
    }


    /**
     * Notes:我得订单
     *
     * DateTime: 2024/1/19 16:57
     */
    public function myOrder(){
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):10;
        switch ($this->site){
            case 1:
                $orderModel = new VipOrder();
                break;
            case 2:
                $orderModel = new VipOrder2();
                break;
            case 3:
                $orderModel = new VipOrder3();
                break;
            case 4:
                $orderModel = new VipOrder4();
                break;
        }
        $lists = $orderModel::lists($uid,$page,$limit);
        return show(1,$lists);
    }
}