<?php

namespace app\payment;

use app\payment\Epay;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PayFactory
{
    public static function create($type)
    {
        switch ($type) {
            case 'epay':
                return new Epay();
            case 'wxr':
                return new Wxr();
            case 'guozhi': 
                return new Guozhi();
            default:
                throw new \Exception("Unknown pay type");
        }
    }
    public static function request($method, $url, $data = [], $headers = [], $type = 'json', $timeout = 10)
    {
        $client = new Client([
            'timeout' => $timeout,
        ]);

        $options = [];

        // 设置请求体
        if (strtolower($type) === 'json') {
            $options['json'] = $data;
        } else {
            $options['form_params'] = $data;
        }

        // 设置请求头
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        try {
            $response = $client->request(strtoupper($method), $url, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        } catch (RequestException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ];
        }
    }
}
