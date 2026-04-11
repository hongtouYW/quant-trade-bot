<?php

namespace app\service;

use Exception;
use GuzzleHttp\Client;

class ImageTranslateService
{
    protected string $apiUrl = 'http://172.247.193.58:8899/transcribes';
    protected int $apiTimeout = 60;
    // protected array $defaultLanguages = ["ru", "vi", "hi", "en", "es"];
    protected array $defaultLanguages = ["en"];
    protected int $thirdPartyId = 1;

    public function translateImage($images, $capterId, $languages = null)
    {
        $client = new Client(['timeout' => $this->apiTimeout]);

        $payload = [
            'external_id'    => (string)$capterId,
            'images'         => $images,
            'languages'      => $languages ?? $this->defaultLanguages,
            'third_party_id' => $this->thirdPartyId,
        ];

        try {
            $res = $client->post($this->apiUrl, [
                'json' => $payload
            ]);

            $body = $res->getBody()->getContents();
            $data = json_decode($body, true);

            if (!isset($data['code'])) {
                sendTelegramMessage(
                    "*图片翻译请求失败* ❌\n" .
                        "- capter_id: `{$capterId}`\n" .
                        "- 响应内容:\n```\n" . mb_substr($body, 0, 1000) . "\n```",
                    2
                );
                save_log("图片翻译请求失败 - capter_id={$capterId}, 响应: " . $body, "translate");
                return null;
            }

            // ✅ code=200（新建任务成功） 或 code=409（已有任务进行中） 都算“有效返回”
            if (in_array($data['code'], [200, 409])) {
                return $data;
            }

            sendTelegramMessage(
                "*图片翻译请求失败* ❌\n" .
                    "- capter_id: `{$capterId}`\n" .
                    "- 响应内容:\n```\n" . mb_substr($body, 0, 1000) . "\n```",
                2
            );
            save_log("图片翻译请求失败 - capter_id={$capterId}, 响应: " . $body, "translate");
            return null;
        } catch (Exception $e) {
            sendTelegramMessage(
                "*图片翻译接口异常* 🚨\n" .
                    "- capter_id: `{$capterId}`\n" .
                    "- 错误信息: `" . $e->getMessage() . "`\n" .
                    "- 参数: `" . json_encode($payload, JSON_UNESCAPED_UNICODE) . "`",
                2
            );
            save_log("图片翻译接口异常 - capter_id={$capterId}, 错误: " . $e->getMessage() . ', 参数: ' . json_encode($payload), "translate");
            return null;
        }
    }
}
