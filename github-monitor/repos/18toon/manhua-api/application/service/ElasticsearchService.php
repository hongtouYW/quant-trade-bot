<?php

namespace app\service;

use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class ElasticsearchService
{
    const INDEX_PREFIX = 'manhua';
    protected static $client = null;
    protected static $allowedLangs = ['zh', 'en'];

    public static function client()
    {
        if (is_null(self::$client)) {
            $config = config('elasticsearch.');

            // 创建禁用 SSL 验证的 Guzzle 客户端
            $handler = HandlerStack::create();
            $guzzleClient = new Client([
                'handler' => $handler,
                'verify' => $config['verify_ssl'] ?? true,
            ]);

            self::$client = ClientBuilder::create()
                ->setHosts([$config['host']])
                ->setBasicAuthentication($config['username'], $config['password'])
                ->setHttpClient($guzzleClient)
                ->build();
        }
        return self::$client;
    }

    public static function getIndexName(string $langCode): string
    {
        return self::INDEX_PREFIX . '_' . strtolower($langCode) . '_index';
    }
}
