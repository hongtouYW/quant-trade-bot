<?php

namespace app\payment;

use app\index\model\ExchangeRate;
use app\payment\contracts\PayInterface;
use app\lib\exception\BaseException;
use app\extra\Rsa;

class Guozhi extends PayBase implements PayInterface
{
    protected $apiUrl;
    protected $currency;

    const REQUIRED_FIELDS = [
        'amount',
        'reference',
    ];

    public function __construct()
    {
        $this->apiUrl = "https://api.ymodr15qo.com/payment/gateway";
        $this->currency = "RMB";
    }

    public function create(array $order, array $gateway)
    {
        $this->validateOrder($order);

        // 获取汇率
        $rate = ExchangeRate::where('currency', $this->currency)->value('rate');
        if (!$rate || $rate <= 0) {
            payment_log("Guozhi支付失败 - reference={$order['reference']}, 未配置{$this->currency}汇率", "guozhi/request_fail");
            throw new BaseException(500, "未配置 {$this->currency} 的汇率");
        }

        // 金额换算
        $convertedAmount = round($order['amount'] * $rate, 2);

        // 是否扣量
        $is_kl = $order['is_kl'] ?? 0;

        if ($is_kl == 0) {
            $tcConfig = [
                'merchant_id' => 'D22',
                'app_id'      => '18tooncn',
                'key_file'    => '../public/key/key_22_18tooncn.txt',
            ];
        } else {
            $tcConfig = [
                'merchant_id' => 'D22',
                'app_id'      => '18tooncn_kl',
                'key_file'    => '../public/key/key_22_18tooncn.txt',
            ];
        }

        // 拼接请求数据
        $data = [
            'amount'      => $convertedAmount,
            'app_id'      => $tcConfig['app_id'],
            'merchant_id' => $tcConfig['merchant_id'],
            'order_no'    => $order['reference'],
            'pay_type'    => $gateway['qudaonum'],
            'currency'    => strtolower($this->currency),
            'timestamp'   => time(),
            'ip_address'  => request()->ip(),
        ];

        // 生成签名
        $string   = get_string($data);
        $key_file = $tcConfig['key_file'];

        try {
            $fn         = fopen($key_file, 'r');
            $key_detail = fread($fn, filesize($key_file));
            fclose($fn);

            $rsa  = new Rsa();
            $sign = $rsa->encrypt($string, $key_detail);
        } catch (\Exception $e) {
            payment_log("Guozhi签名失败 - reference={$order['reference']}, 错误: " . $e->getMessage(), "guozhi/request_fail");
            throw new BaseException(500, "Guozhi签名失败: " . $e->getMessage());
        }

        $data['encode_sign'] = $sign;

        // 请求日志
        payment_log("Guozhi订单发起 - reference={$order['reference']}, 请求数据: " . json_encode($data, JSON_UNESCAPED_UNICODE), "guozhi/request");

        $result = httpPost($this->apiUrl, $data);
        $arr    = json_decode($result, true);

        // 校验响应
        if (!isset($arr['code']) || $arr['code'] != 0) {
            $msg = $arr['msg'] ?? '支付接口请求失败';
            payment_log("Guozhi支付失败 - reference={$order['reference']}, 响应: " . json_encode($arr, JSON_UNESCAPED_UNICODE), "guozhi/request_fail");
            throw new BaseException(5003, "Guozhi支付失败: {$msg}");
        }

        // 成功日志
        payment_log("Guozhi订单创建成功 - reference={$order['reference']}, 响应: " . json_encode($arr, JSON_UNESCAPED_UNICODE), "guozhi/request");

        return [
            'currency' => strtolower($this->currency),
            'pay_url'  => $arr['data']['url'] ?? '',
            'amount'   => $convertedAmount,
        ];
    }

    protected function validateOrder(array $order)
    {
        foreach (self::REQUIRED_FIELDS as $key) {
            if (empty($order[$key])) {
                throw new BaseException(400, "缺少必要参数：{$key}");
            }
        }
    }
}
