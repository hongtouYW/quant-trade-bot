<?php

namespace app\index\model;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Model;
use think\Db;

class User extends Model
{
    protected $name = 'user';
    protected  $hidden = ['pid','coin','password','login_time','status','stop','p','vip_begin_time','wm_begin_time','dm_begin_time','k4_begin_time'];



    public function getVipEndTimeAttr($value){
        if(empty($value)){
            return 0;
        }
        return (int)$value;
    }


    public function getIsVip1Attr($val,$data){
        $val = 0;
        $time = time();
        if($data['vip_end_time'] > $time){
            $val = 1;
        }
        return $val;
    }

    public function getIsVip2Attr($val,$data){
        $val = 0;
        $time = time();
        if($data['wm_end_time'] > $time){
            $val = 1;
        }
        return $val;
    }


    public function getIsVip3Attr($val,$data){
        $val = 0;
        $time = time();
        if($data['dm_end_time'] > $time){
            $val = 1;
        }
        return $val;

    }


    public function getIsVip4Attr($val,$data){
        $val = 0;
        $time = time();
        if($data['k4_end_time'] > $time){
            $val = 1;
        }
        return $val;

    }

    public static function getUserInfo($id){
        return self::where('id','=',$id)->find()->append(['is_vip1','is_vip2','is_vip3','is_vip4']);
    }

    public function getAvatarAttr($value){
        return Configs::get('default_avatar');
    }


    public static function is_vip($id){
        $vip_end_time = self::where('id','=',$id)->value('vip_end_time');
        return $vip_end_time > time() ? 1 : 0;
    }

    public static function is_vip1($id){
        $wm_end_time = self::where('id','=',$id)->value('wm_end_time');
        return $wm_end_time > time() ? 1 : 0;
    }
    public static function is_vip2($id){
        $dm_end_time = self::where('id','=',$id)->value('dm_end_time');
        return $dm_end_time > time() ? 1 : 0;
    }
    public static function is_vip3($id){
        $k4_end_time = self::where('id','=',$id)->value('k4_end_time');
        return $k4_end_time > time() ? 1 : 0;
    }

    public static function addLog($uid)
    {
        $ip         = get_client_ip();
        $agent      = request()->header('user-agent');
        $deviceInfo = self::parseDeviceInfo($agent);
        $time       = time();

        $deviceId = Db::name('devices_log')->insertGetId([
            'uid'         => $uid,
            'device_name' => mb_substr((string)$agent, 0, 500),
            'device_type' => $deviceInfo['device_type'],
            'os_version'  => $deviceInfo['os_version'],
            'created_at'  => $time,
        ]);

        $location = self::getIpLocation($ip);

        Db::name('login_log')->insert([
            'uid'        => $uid,
            'ip'         => $ip,
            'device_log' => $deviceId,
            'country'    => $location['country'],
            'province'   => $location['province'],
            'city'       => $location['city'],
            'latitude'   => $location['latitude'],
            'longitude'  => $location['longitude'],
            'created_at' => $time,
        ]);
    }

    private static function getIpLocation($ip)
    {
        // $empty = ['country' => '', 'province' => '', 'city' => '', 'latitude' => null, 'longitude' => null];

        // if (empty($ip) || $ip === '0.0.0.0') return $empty;

        // // Cache in Redis for 7 days to avoid hitting rate limits
        // $cacheKey = 'ip_loc:' . $ip;
        // $redis = new \think\cache\driver\Redis();
        // $cached = $redis->get($cacheKey);
        // if ($cached) return $cached;

        // // ip-api.com: works in China mainland, 1000 req/min free, no HTTPS needed on free tier
        // $url      = "http://ip-api.com/json/{$ip}?fields=country,regionName,city,lat,lon&lang=zh-CN";
        // $ctx      = stream_context_create(['http' => ['timeout' => 3]]);
        // $response = @file_get_contents($url, false, $ctx);
        // $data     = $response ? json_decode($response, true) : null;

        // if (empty($data) || ($data['status'] ?? '') === 'fail') {
        //     return $empty;
        // }

        // $result = [
        //     'country'   => $data['country'] ?? '',
        //     'province'  => $data['regionName'] ?? '',
        //     'city'      => $data['city'] ?? '',
        //     'latitude'  => $data['lat'] ?? null,
        //     'longitude' => $data['lon'] ?? null,
        // ];

        // $redis->set($cacheKey, $result, 604800); // cache 7 days
        // return $result;
        return ['country' => '', 'province' => '', 'city' => '', 'latitude' => null, 'longitude' => null];
    }

    public static function parseDeviceInfo($ua) {
        $deviceType = '';
        $osVersion = '';
        // Android
        if (preg_match('/Android\s([0-9\.]+)/i', $ua, $m)) {
            $deviceType = 'Android';
            $osVersion = $m[1];
        }
        // iPhone
        if (preg_match('/iPhone.*OS\s([0-9\_]+)/i', $ua, $m)) {
            $deviceType = 'iPhone';
            $osVersion = str_replace('_', '.', $m[1]);
        }
        // Windows
        if (preg_match('/Windows NT\s([0-9\.]+)/i', $ua, $m)) {
            $deviceType = 'Windows';
            $osVersion = $m[1];
        }
        return [
            'device_type' => $deviceType,
            'os_version'  => $osVersion,
        ];
    }
}