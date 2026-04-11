<?php

namespace app\payment;

use app\payment\PayFactory;

abstract class PayBase
{
    protected function request($method, $url, $data = [], $headers = [], $type = 'form', $timeout = 10)
    {
        return PayFactory::request($method, $url, $data, $headers, $type, $timeout);
    }
}
