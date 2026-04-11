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

    public static function getMappingFields()
    {
        return [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'text'],
            'desc' => ['type' => 'text'],
        ];
    }

    protected static function isLangAllowed(string $langCode): bool
    {
        return in_array(strtolower($langCode), self::$allowedLangs);
    }

    public static function buildIndexBody(array $manhua): array
    {
        $fields = array_keys(self::getMappingFields());
        $body = [];
        foreach ($fields as $field) {
            $body[$field] = $manhua[$field] ?? null;
        }
        return $body;
    }

    public static function indexManhua(array $manhua, string $langCode)
    {
        if (!self::isLangAllowed($langCode)) {
            return ['result' => 'skipped', 'reason' => "Language '$langCode' not allowed."];
        }

        $params = [
            'index' => self::getIndexName($langCode),
            'id' => $manhua['id'],
            'body' => self::buildIndexBody($manhua)
        ];
        return self::client()->index($params);
    }

    public static function deleteManhua(int $id, string $langCode)
    {
        if (!self::isLangAllowed($langCode)) {
            return ['result' => 'skipped', 'reason' => "Language '$langCode' not allowed."];
        }

        $index = self::getIndexName($langCode);
        $client = self::client();

        if ($client->exists(['index' => $index, 'id' => $id])->asBool()) {
            return $client->delete([
                'index' => $index,
                'id' => $id
            ]);
        }

        return ['result' => 'not_found'];
    }

    public static function clearManhuaIndex(string $langCode)
    {
        if (!self::isLangAllowed($langCode)) {
            return ['result' => 'skipped', 'reason' => "Language '$langCode' not allowed."];
        }

        $index = self::getIndexName($langCode);
        $client = self::client();

        // 判断索引是否存在
        if ($client->indices()->exists(['index' => $index])->asBool()) {
            $client->indices()->delete(['index' => $index]);
        }

        // 重新创建索引结构
        $client->indices()->create([
            'index' => $index,
            'body' => [
                'mappings' => [
                    'properties' => self::getMappingFields()
                ]
            ]
        ]);
    }
}
