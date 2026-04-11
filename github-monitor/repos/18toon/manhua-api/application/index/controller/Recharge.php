<?php

namespace app\index\controller;

use app\extra\Rsa;
use app\index\model\Order;
use app\index\model\Paymanger;
use app\index\model\Pro;
use app\index\model\Token;
use app\index\model\Config as ConfigModel;
use app\index\model\User as UserModel;
use app\service\ChannelStatsService;
use app\lib\exception\BaseException;
use app\payment\PayFactory;
use think\cache\driver\Redis;

class Recharge extends Base
{
    public $platform1 = "Guozhi";
    public $platform2 = 'Wxr';



    /**
     * Notes:充值列表
     *
     * DateTime: 2023/11/8 16:16
     */
    public function lists()
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Pro::lists($lang);
        return show(1, $lists);
    }

    /**
     * Notes:充值渠道
     *
     * DateTime: 2023/11/8 16:26
     */
    public function platforms()
    {
        $lang = getInput('lang') ?: 'zh';

        $proId = getInput('pro_id') ?: 0;
        $price = null;
        if ($proId > 0) {
            $proInfo = Pro::where('id', $proId)->field('id,price')->find();
            if ($proInfo) {
                $price = (int) $proInfo['price'];
            }
        }

        $lists = Paymanger::lists($lang)->toArray();

        if ($price === null || $proId === 0) {
            return show(1, $lists);
        }

        // 按金额过滤渠道
        $available = [];
        foreach ($lists as $channel) {
            $moneyRule = trim((string) ($channel['qudaomoney'] ?? ''));

            if ($moneyRule === '' || $moneyRule === '-') {
                $available[] = $channel;
                continue;
            }

            $parts = array_map('trim', explode(',', $moneyRule));
            foreach ($parts as $part) {
                if (strpos($part, '-') !== false) {
                    // 区间 "10-20"
                    [$min, $max] = array_map('intval', explode('-', $part, 2));
                    if ($price >= $min && $price <= $max) {
                        $available[] = $channel;
                        break;
                    }
                } else {
                    // 单值 "50"
                    if ($price === (int) $part) {
                        $available[] = $channel;
                        break;
                    }
                }
            }
        }
        if (!$available) {
            throw new BaseException(5002, '支付通道不存在');
        }

        return show(1, $available);
    }


    /**
     * Notes:充值
     *
     * DateTime: 2023/11/8 16:30
     */
    public function pay()
    {
        $uid = Token::getCurrentUid();
        //检查冷却
        $redisKey = 'pay_' . $uid;
        $redis = new Redis();
        $cooldown = $redis->get($redisKey);
        if ($cooldown)
            throw new BaseException(5004);
        ;

        $proId = getInput('pro_id');
        $proInfo = Pro::where('id', '=', $proId)->field('id,price,title')->find();
        if (!$proInfo) {
            throw new BaseException(5001);
        }

        $payId = getInput('pay_id');

        $payInfo = Paymanger::where('id', '=', $payId)
            ->field('id,qudaonum,qudaokey,config')
            ->find();

        if (!$payInfo) {
            throw new BaseException(5002, '支付通道不存在');
        }

        $payInfo = $payInfo->toArray();

        $num = mt_rand(1, 1000);
        $kouliang = (int) ConfigModel::where('id', '=', '13')->value('value');
        if ($num > $kouliang) {
            $is_kl = 0;
        } else {
            $is_kl = 1;
        }

        $orderSn = date('YmdHis') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);

        $bounded = $redis->get('order_bounded_' . $uid);
        $order = [
            'member_id' => $uid,
            'pro_id' => $proId,
            'paydata' => $payId,
            'ordernums' => $orderSn,
            'addtime' => date('Y-m-d H:i:s'),
            'money' => $proInfo['price'],
            'addtime2' => time(),
            'pay_ip' => request()->ip(),
            'bounded' => $bounded,
            'is_kl' => $is_kl,
            'currency' => null,
            'pay_amount' => null,
            'discount' => 0,
            'card_no' => getInput('card_ccno'),
            'email' => getInput('bill_email'),
        ];

        try {
            // $user = UserModel::field('viptime,isvip_status,discount_time')->where('id', '=', $uid)->find();

            // if ($user['discount_time'] > time()) {
            //     $order['discount'] = 1;
            // } else {
            //     if ($user['isvip_status'] == 0) {
            //         UserModel::where('id', '=', $uid)->update(['discount_time' => time() + 600]);
            //     }
            // }
            Order::insert($order);
            $channelId = ChannelStatsService::getValidChannelIdByUser($uid);
            if (!empty($channelId)) {
                ChannelStatsService::recordRecharge($proInfo['price'], 0, (int) $channelId);
            }
        } catch (\Exception $e) {
            throw new BaseException(999);
        }

        $payParams = [
            'amount' => $proInfo['price'],
            'reference' => $orderSn,
            'card_type' => getInput('card_type'),
            'card_ccno' => getInput('card_ccno'),
            'card_ccvv' => getInput('card_ccvv'),
            'card_month' => getInput('card_month'),
            'card_year' => getInput('card_year'),
            'bill_fullname' => getInput('bill_fullname'),
            'bill_email' => getInput('bill_email'),
            'bill_address' => getInput('bill_address'),
            'bill_city' => getInput('bill_city'),
            'bill_state' => getInput('bill_state'),
            'bill_country' => getInput('bill_country'),
            'bill_zip' => getInput('bill_zip'),
            'bill_phone' => getInput('bill_phone'),
        ];

        // 调用支付工厂
        $payObject = PayFactory::create($payInfo['qudaokey']);
        $result = $payObject->create($payParams, $payInfo);

        if (empty($result)) {
            throw new BaseException(5003);
        }

        Order::where('ordernums', $orderSn)->update([
            'currency' => $result['currency'],
            'pay_amount' => $result['amount']
        ]);

        //返回参数
        $returnData = [];
        $returnData['pay_url'] = $result['pay_url'];
        $returnData['order_id'] = $orderSn;
        //设置冷却时间
        $redis->set($redisKey, 1, 20);
        return show(1, $returnData);
    }


    //唐朝支付
    public function getGuozhiUrl($amount, $order_sn, $pay_info, $is_kl, $uid)
    {
        $url = 'https://api.ymodr15qo.com/payment/gateway';
        if ($is_kl == 0) {
            $tcConfig = [
                'merchant_id' => 'J16',
                'app_id' => 'manhua',
                'key_file' => '../public/key/key_16_manhua.txt',
            ];
        } else if ($is_kl == 1) {
            $tcConfig = [
                'merchant_id' => 'R4',
                'app_id' => 'manhua',
                'key_file' => '../public/key/key_4_manhua.txt'
            ];
        }
        $data = [
            'amount' => $amount,
            'app_id' => $tcConfig['app_id'],
            'merchant_id' => $tcConfig['merchant_id'],
            'order_no' => $order_sn,
            'pay_type' => $pay_info['qudaonum'],
            'currency' => 'rmb',
            'timestamp' => time(),
            'ip_address' => request()->ip()
        ];

        $string = get_string($data);
        $key_file = $tcConfig['key_file'];
        //Sign data
        try {
            $fn = fopen($key_file, 'r');
            $key_detail = fread($fn, filesize($key_file));
            fclose($fn);
            $rsa = new Rsa();
            $sign = $rsa->encrypt($string, $key_detail);
        } catch (\Exception $e) {
            return "";
        }
        $data['encode_sign'] = $sign;
        $result = httpPost($url, $data);
        $arr = json_decode($result, JSON_UNESCAPED_UNICODE);
        if ($arr['code'] != 0) {
            save_log($arr, "makepaymentgz_error_" . $is_kl);
            return "";
        }
        $redis_key = 'order_bounded_' . $uid;
        $redis = new Redis();
        $redis->set($redis_key, $arr['data']['bounded'], 60); //2小时
        $returnURL = $arr['data']['url'];
        return $returnURL;
    }


    //外星人支付
    public function getWxrUrl($amount, $order_sn, $pay_info, $is_kl, $uid)
    {
        $apiUrl = ConfigModel::get('API_URL');
        $url = 'https://api.oojloy388.com/process/pay';

        if ($is_kl == 0) {
            $yfConfig = [
                'partnerId' => 'C10011V',
                'project' => 'manhua',
                'Md5key' => 'D8GM6XUK529JWP'
            ];
            $is_kl = 0;
        } else if ($is_kl == 1) {
            $yfConfig = [
                'partnerId' => 'H4B',
                'project' => 'manhua',
                'Md5key' => 'PO6YGWFIIJ0ZG1'
            ];
        }

        $postData = [
            "partnerId" => $yfConfig['partnerId'],
            "orderId" => $order_sn,
            "amount" => $amount,
            "ip" => request()->ip(),
            "notifyUrl" => $apiUrl . 'notify/callbackwxr',
            "returnUrl" => $apiUrl . 'notify/successwxr', //页面跳转同步通知页面路径
            "payType" => $pay_info['qudaonum'],
            "project" => $yfConfig['project'],
        ];
        $Md5key = $yfConfig['Md5key'];
        ksort($postData);                               //ASCII码排序
        reset($postData);                               //定位到第一个下标
        $md5str = "";
        foreach ($postData as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $md5str = substr($md5str, 0, -1);
        $sign = strtoupper(md5($md5str . $Md5key));
        $postData["sign"] = $sign;

        $result = httpPost($url, $postData);
        $result = json_decode($result, JSON_UNESCAPED_UNICODE);
        if ($result['code'] == "0") {
            $returnURL = $result['data']['payUrl'];
            $redis_key = 'order_bounded_' . $uid;
            $redis = new Redis();
            $redis->set($redis_key, $result['data']['bounded'], 60); //2小时
            return $returnURL;
        } else {
            save_log($result, "makepaymentwxr_error_" . $is_kl);
            return "";
        }
    }
}
