<?php

namespace app\index\model;
use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Model;
use think\facade\Config;
use app\index\model\Configs;
use think\Db;

class User extends Model
{
    protected $name = 'user';
    protected  $hidden = ['pid', 'password','login_time','status','stop','p','vip_begin_time'];
    // protected  $hidden = ['pid','coin', 'point', 'password','login_time','status','stop','p','vip_begin_time','wm_begin_time','dm_begin_time','k4_begin_time'];



    public function getVipEndTimeAttr($value){
        if(empty($value)){
            return 0;
        }
        return (int)$value;
    }


    public function getIsVipAttr($val,$data){
        $val = 0;
        $time = time();
        if($data['vip_end_time'] > $time){
            $val = 1;
        }
        return $val;
    }

    public static function getUserInfo($id){
        return self::where('id','=',$id)->find()->append(['is_vip']);
    }

    public function getAvatarAttr($value){
        return Configs::get('default_avatar');
    }

    public static function addLog($uid)
    {
        $ip         = request()->ip();
        $agent      = request()->header('user-agent');
        $deviceInfo = self::parseDeviceInfo($agent);
        $time       = time();

        $deviceId = Db::name('devices_log')->insertGetId([
            'uid'         => $uid,
            'device_name' => $agent,
            'device_type' => $deviceInfo['device_type'],
            'os_version'  => $deviceInfo['os_version'],
            'created_at'  => time(),
        ]);

        $url      = "https://ipinfo.io/{$ip}/json";
        $response = @file_get_contents($url);
        $location = json_decode($response, true);
        $country  = $location['country'] ?? '';
        $province = $location['region'] ?? '';
        $city     = $location['city'] ?? '';
        $latitude  = null;
        $longitude = null;

        if (!empty($location['loc'])) {
            list($latitude, $longitude) = explode(',', $location['loc']);
        }

        Db::name('login_log')->insert([
            'uid'        => $uid,
            'ip'         => $ip,
            'device_log' => $deviceId,
            'country'    => $country,
            'province'   => $province,
            'city'       => $city,
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'created_at' => $time,
        ]);
    }


    public static function is_vip($id){
        $vip_end_time = self::where('id','=',$id)->value('vip_end_time');
        return $vip_end_time > time() ? 1 : 0;
    }

    public static function hasVideoAccess($uid, $videoInfo)
    {
        if (!empty($videoInfo['private'] == 0)) {
            return true;
        }

        $purchase = VideoPurchase::where('uid', $uid)
            ->where('video_id', $videoInfo['id'])
            ->find();

        if ($purchase) {
            return true;
        }

        if (!empty($videoInfo['publish_date'])) {
            return self::hasVipAccessToDate($uid, $videoInfo['publish_date']);
        }

        return false;
    }

    public static function hasVipAccessToDate($uid, $publishDate) 
    {
        $user = self::where('id', $uid)->find();
        if (!$user || empty($user['vip_end_time'])) {
            return false;
        }

        $vipVideoDuration = (int) Configs::get('vip_video_duration');
        // $vipVideoDuration = (int) Config::get('app.vip_video_duration', 12);
        $vipBeginTime     = (int) $user['vip_begin_time'];
        $vipEndTime       = (int) $user['vip_end_time'];
        $publishTimestamp = is_numeric($publishDate) ? (int)$publishDate : strtotime($publishDate);

        if (time() > $vipEndTime) { // dont check due if admin update user vip_end_time from back office, the vip_begin_time may null/0
            return false; // vip expired or havent start
        }
        $videoStartTime = strtotime("-$vipVideoDuration months", time());
        return $publishTimestamp >= $videoStartTime && $publishTimestamp <= time();
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