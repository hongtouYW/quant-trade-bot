<?php

namespace app\payment;

use app\index\model\ExchangeRate;
use app\payment\contracts\PayInterface;
use app\lib\exception\BaseException;

class Epay extends PayBase implements PayInterface
{
    protected $publicKey;
    protected $privateKey;
    protected $apiUrl;
    protected $terNO;
    protected $webhookUrl;
    protected $returnUrl;
    protected $currency;

    const REQUIRED_FIELDS = [
        'amount',
        'reference',
        'card_type',
        'card_ccno',
        'card_ccvv',
        'card_month',
        'card_year',
        'bill_fullname',
        'bill_email',
        'bill_address',
        'bill_city',
        'bill_state',
        'bill_country',
        'bill_zip',
        'bill_phone'
    ];

    public function __construct()
    {
        $this->publicKey = "MTE3NDlfOTcxXzIwMjUwNzI4MTgxNjAw";
        $this->privateKey = "MTE3NDlfMjAyNTA3MjgxODE1MzQ";
        $this->apiUrl = "https://gtw.online-epayment.com/directapi";
        $this->terNO = "971";
        $this->currency = "USD";
        $this->returnUrl = 'https://18toon.vip/';
        $this->webhookUrl = 'https://newmaomimhapi.9xyrp3kg4b86.com/callback/epayCallback';
    }

    public function create(array $order, array $gateway)
    {
        // ✅ Step 1: 参数校验
        $this->validateOrder($order);

        // ✅ Step 2: 获取汇率
        $rate = ExchangeRate::where('currency', $this->currency)->value('rate');
        if (!$rate || $rate <= 0) {
            payment_log("Epay支付失败 - reference={$order['reference']}, 未配置{$this->currency}汇率", "epay/request_fail");
            throw new BaseException(500, "未配置 {$this->currency} 的汇率");
        }

        // ✅ Step 3: 计算汇率后金额
        $convertedAmount = round($order['amount'] * $rate, 2);

        // ✅ Step 4: 构造数据
        $data = [
            'public_key' => $this->publicKey,
            'terNO' => $this->terNO,
            'integration-type' => "s2s",
            'bill_ip' => request()->ip(),
            'source' => "Curl-Direct-Card-Payment",
            'source_url' => "https://18toon.vip/",
            'bill_amt' => $convertedAmount,
            'bill_currency' => $this->currency,
            'product_name' => 'VIP',
            'fullname' => $order['bill_fullname'],
            'bill_email' => $order['bill_email'],
            'bill_address' => $order['bill_address'],
            'bill_city' => $order['bill_city'],
            'bill_state' => $order['bill_state'],
            'bill_country' => $order['bill_country'],
            'bill_zip' => $order['bill_zip'],
            'bill_phone' => $order['bill_phone'],
            'reference' => $order['reference'],
            'webhook_url' => $this->webhookUrl,
            'return_url' => $this->returnUrl,
            'mop' => strtoupper($order['card_type']),
            'ccno' => $order['card_ccno'],
            'ccvv' => $order['card_ccvv'],
            'month' => $order['card_month'],
            'year' => $order['card_year'],
        ];

        // ✅ Step 5: 加密
        $encrypted = $this->payloadSha256(http_build_query($data));
        if (!$encrypted) {
            payment_log("Epay支付失败 - reference={$order['reference']}, 加密失败", "epay/request_fail");
            throw new BaseException(500, "加密失败");
        }

        // ✅ Step 6: 发起请求
        $result = $this->request('POST', $this->apiUrl, [
            'encrypted_payload' => $encrypted . $this->publicKey
        ]);

        // ✅ Step 7: 检查响应
        if (empty($result['authurl'])) {
            payment_log("Epay支付失败 - reference={$order['reference']}, 响应: " . json_encode($result, JSON_UNESCAPED_UNICODE), "epay/request_fail");
            throw new BaseException(5003, 'Epay 返回无支付链接');
        }

        payment_log("Epay订单创建成功 - reference={$order['reference']}, 响应: " . json_encode($result, JSON_UNESCAPED_UNICODE), "epay/request");

        return [
            'currency' => strtolower($this->currency),
            'pay_url' => $result['authurl'],
            'amount' => $convertedAmount, // ✅ 建议返回转换后的金额
        ];
    }

    /**
     * AES 加密并 base64 格式化
     */
    protected function payloadSha256($string)
    {
        $iv = substr(hash('sha256', $this->publicKey), 0, 16);
        $encrypted = openssl_encrypt($string, "AES-256-CBC", $this->privateKey, 0, $iv);

        if ($encrypted === false) {
            return null;
        }

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    /**
     * 校验订单字段
     */
    protected function validateOrder(array $order)
    {
        foreach (self::REQUIRED_FIELDS as $key) {
            if (empty($order[$key])) {
                throw new BaseException(400, "缺少必要参数：{$key}");
            }
        }

        // 信用卡字段验证
        if (!preg_match('/^\d{16}$/', $order['card_ccno'])) {
            throw new BaseException(400, "信用卡号应为16位数字");
        }

        if (!preg_match('/^\d{3,4}$/', $order['card_ccvv'])) {
            throw new BaseException(400, "CVV格式应为3-4位数字");
        }

        if (!preg_match('/^\d{1,2}$/', $order['card_month']) || $order['card_month'] < 1 || $order['card_month'] > 12) {
            throw new BaseException(400, "月份格式错误，应为1-12之间");
        }

        if (!preg_match('/^\d{2}$/', $order['card_year'])) {
            throw new BaseException(400, "年份格式应为两位数");
        }

        if (!filter_var($order['bill_email'], FILTER_VALIDATE_EMAIL)) {
            throw new BaseException(400, "邮箱格式错误");
        }

        $allowedCardTypes = ['CC', 'DC']; // 信用卡 / 借记卡

        if (!in_array(strtoupper($order['card_type']), $allowedCardTypes)) {
            throw new BaseException(400, "不支持的卡片类型，请使用 CC 或 DC");
        }
    }
}
