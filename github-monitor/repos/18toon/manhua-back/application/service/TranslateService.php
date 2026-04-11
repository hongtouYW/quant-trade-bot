<?php

namespace app\service;

use GuzzleHttp\Client;

class TranslateService
{
    protected $apiUrl = 'http://69.30.85.237:22021/translate';
    protected $timeout = 60;

    protected $languages = [
        'ru' => 'Russian',
        'vi' => 'Vietnamese',
        'hi' => 'Hindi',
        'en' => 'English',
        'es' => 'Spanish',
    ];

    public function getLanguageName($code)
    {
        return $this->languages[$code] ?? null;
    }

    public function translate($text, $langCode)
    {
        $languageName = $this->getLanguageName($langCode);
        if (!$languageName) {
            throw new \Exception("Unsupported language code: {$langCode}");
        }

        $client = new Client(['timeout' => $this->timeout]);
        $res = $client->post($this->apiUrl, [
            'json' => [
                'text' => $text,
                'target_language' => $languageName
            ]
        ]);

        $body = json_decode((string)$res->getBody(), true);
        return $body['translated_text'] ?? '';
    }
}
