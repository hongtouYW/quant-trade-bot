<?php
namespace app\service;

use think\facade\Log;
use app\index\model\StatisticChannel;
use app\index\model\User as UserModel;

class ChannelStatsService
{
    // 固定的 channel 名称（由 Go 项目提供）
    private static $channelName = '';
    private static $secretKey = '';

    private static $baseUrl = 'https://mtj.ast1930.com/api';
    private static $curlTimeout = 3;

    /**
     * 设置渠道信息（可动态）
     */
    public static function setChannelById(int $channelId): bool
    {
        $channel = StatisticChannel::getById($channelId);
        if (!$channel) {
            Log::error("[ChannelStats] Channel not found: {$channelId}");
            return false;
        }
        self::$channelName = $channel->name;
        self::$secretKey = $channel->secret_key;

        return true;
    }

    public static function setChannelByName(string $channelName): bool
    {
        $channel = StatisticChannel::getByName($channelName);
        if (!$channel) {
            Log::error("[ChannelStats] Channel not found by name: {$channelName}");
            return false;
        }

        self::$channelName = $channel->name;
        self::$secretKey = $channel->secret_key;

        return true;
    }

    public static function getValidChannelIdByUser(int $userId): ?int
    {
        $user = UserModel::where('id', $userId)
            ->field('channel_id, channel_expire_time')
            ->find();

        if (empty($user) || empty($user['channel_id'])) {
            return null;
        }

        // 检查渠道是否过期
        if ((int) $user['channel_expire_time'] <= time()) {
            // 渠道过期，清空
            UserModel::where('id', $userId)->update(['channel_id' => null, 'channel_expire_time' => 0]);
            return null;
        }

        return (int) $user['channel_id'];
    }

    /**
     * 生成签名
     */
    private static function makeSign(array $params): string
    {
        if (isset($params['sign']))
            unset($params['sign']);

        foreach ($params as $k => $v) {
            if (is_bool($v))
                $params[$k] = $v ? '1' : '0';
            elseif (is_float($v))
                $params[$k] = rtrim(rtrim(sprintf('%.8f', $v), '0'), '.');
            else
                $params[$k] = (string) $v;
        }

        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            $signStr .= "{$k}={$v}&";
        }
        $signStr .= 'secret_key=' . self::$secretKey;

        return hash('sha256', $signStr);
    }

    /**
     * 统一 POST 请求
     */
    private static function post(string $endpoint, array $params): array
    {
        if (empty(self::$channelName) || empty(self::$secretKey)) {
            Log::error("[ChannelStats] ChannelName or SecretKey not set.");
            return ['ok' => false, 'error' => 'Channel not configured'];
        }

        $params['channel'] = self::$channelName;
        $params['timestamp'] = time();
        $params['sign'] = self::makeSign($params);

        $url = rtrim(self::$baseUrl, '/') . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_TIMEOUT => self::$curlTimeout,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);

        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error("[ChannelStats] curl error: {$err} | {$endpoint}");
            return ['ok' => false, 'error' => $err];
        }

        $decoded = json_decode($resp, true);
        $ok = ($http === 200 && is_array($decoded) &&
            (isset($decoded['status']) && ($decoded['status'] === 'ok' || $decoded['status'] === true)));
        if (!$resp) {
            Log::warning("[ChannelStats] API failed", [
                'endpoint' => $endpoint,
                'resp' => $resp,
                'params' => $params
            ]);
        }
        return ['ok' => $ok, 'response' => $decoded ?? $resp];
    }

    /**
     * 注册上报
     */
    public static function recordRegister(?int $channelId = null)
    {
        $params = [];
        if ($channelId && !self::setChannelById($channelId)) {
            Log::warning("[ChannelStats] recordRegister channel not found: {$channelId}");
            return;
        }

        return self::post('/register', $params);
    }

    /**
     * 登录上报
     */
    public static function recordLogin(?int $channelId = null)
    {
        $params = [];
        if ($channelId && !self::setChannelById($channelId)) {
            Log::warning("[ChannelStats] recordRegister channel not found: {$channelId}");
            return;
        }
        return self::post('/login', $params);
    }

    /**
     * 充值上报
     */
    public static function recordRecharge($amount, $status = 0, ?int $channelId = null)
    {
        $params = [
            'amount' => (float) $amount,
            'status' => $status,
        ];
        if ($channelId && !self::setChannelById($channelId)) {
            Log::warning("[ChannelStats] recordRecharge channel not found: {$channelId}");
            return;
        }

        return self::post('/recharge', $params);
    }

    /**
     * 访问上报
     */
    public static function recordVisit($ip = null)
    {
        $params = [];
        if (!empty($ip))
            $params['ip'] = $ip;
        return self::post('/visit', $params);
    }
}
