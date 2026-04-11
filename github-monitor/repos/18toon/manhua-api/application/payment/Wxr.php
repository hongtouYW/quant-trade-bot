<?php

namespace app\payment;

use app\index\model\ExchangeRate;
use app\index\model\Config as ConfigModel;
use app\payment\contracts\PayInterface;
use app\lib\exception\BaseException;

class Wxr extends PayBase implements PayInterface
{
    protected $apiUrl;
    protected $currency;

    const REQUIRED_FIELDS = [
        'amount',
        'reference',
    ];

    public function __construct()
    {
        $this->apiUrl = "https://api.oojloy388.com/process/pay";
        $this->currency = "RMB";
    }

    public function create(array $order, array $gateway)
    {
        $this->validateOrder($order);

        $rate = ExchangeRate::where('currency', $this->currency)->value('rate');
        if (!$rate || $rate <= 0) {
            payment_log("Wxr支付失败 - reference={$order['reference']}, 未配置{$this->currency}汇率", "wxr/request_fail");
            throw new BaseException(500, "未配置 {$this->currency} 的汇率");
        }

        $convertedAmount = round($order['amount'] * $rate, 2);
        $apiUrl = rtrim(ConfigModel::get('API_URL'), '/') . '/';

        $gatewayConfig = json_decode($gateway['config'] ?? '{}', true);
        if (empty($gatewayConfig)) {
            payment_log("Wxr支付失败 - reference={$order['reference']}, 渠道配置无效", "wxr/request_fail");
            throw new BaseException(500, '支付渠道配置(config)无效或未设置');
        }

        $data = [
            "partnerId" => $gatewayConfig['partner_id'],
            "orderId" => $order['reference'],
            "amount" => $convertedAmount,
            "ip" => request()->ip(),
            "notifyUrl"  => $apiUrl . 'callback/wxrCallback',
            "returnUrl"  => $apiUrl . 'callback/wxrCallback',
            "payType" => $gatewayConfig['pay_type'],
            "project" => $gatewayConfig['project'],
        ];
        $data['sign'] = $this->makeSign($data, $gatewayConfig['key']);

        payment_log("Wxr订单发起 - reference={$order['reference']}, 请求数据: " . json_encode($data, JSON_UNESCAPED_UNICODE), "wxr/request");

        $result = $this->request('POST', $this->apiUrl, $data);

        // ✅ Step 5: 响应校验
        if (!isset($result['code']) || $result['code'] != '0') {
            $msg = $result['msg'] ?? '支付接口请求失败';
            payment_log("Wxr支付失败 - reference={$order['reference']}, 响应: " . json_encode($result, JSON_UNESCAPED_UNICODE), "wxr/request_fail");
            throw new BaseException(5003, "Wxr支付失败：{$msg}");
        }

        payment_log("Wxr订单创建成功 - reference={$order['reference']}, 响应: " . json_encode($result, JSON_UNESCAPED_UNICODE), "wxr/request");

        return [
            'currency' => strtolower($this->currency),
            'pay_url'  => $result['data']['payUrl'] ?? '',
            'amount'   => $convertedAmount,
        ];
    }

    protected function makeSign(array $params, string $key): string
    {
        ksort($params);
        $query = http_build_query($params);
        return strtoupper(md5(urldecode($query) . $key));
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
