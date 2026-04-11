<?php

namespace app\payment\contracts;

interface PayInterface
{
    /**
     * 创建支付请求
     */
    public function create(array $order, array $gateway);
}
