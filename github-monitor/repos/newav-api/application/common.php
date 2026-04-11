<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件


use think\facade\Request;
use think\facade\Env;
use app\index\model\User as UserModel;
use app\lib\exception\BaseException;


/**
 * 删除图片
 */
if (!function_exists('delImage')) {
    function delImage($path){
        $img_path = Env::get('root_path').'public/';
        if(!empty($path) && file_exists($img_path.$path))
        { //检查图片文件是否存在
            unlink($img_path.$path);
        }
    }
}

/**
 * 生成随机码
 */

if (!function_exists('getRandChar')) {
    function getRandChar($length,$randomChars='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijkomnopqrstuvwxyz')
    {
        $randomCode = "";
        for ($i = 0; $i < $length; $i++) {
            $randomCode .= $randomChars[mt_rand(0, strlen($randomChars) - 1)];
        }
        return $randomCode;
    }
}


/**
 *接口统一返回
 */
if (!function_exists('show')) {
    function show($code='',$data=[],$httpCode = 200) {
        $result = [
            'code'=>intval($code),
            'msg'=>getMsg($code),
            // 'site'=>(int)getSite(),
            'timestamp'=>time(),
        ];
        $app_status = config('lock.app_lock');
//        $result['crypt'] = $app_status;
        if(!empty($data) || $data == 0){
            $result['data'] = $data;
        }
        if($app_status){
            $result = apps_lock($result);
        }
        return json($result);
    }
}


if (!function_exists('getMsg')) {
    function getMsg($code)
    {
        $lang = getlang();
        $err = require_once Env::get('app_path') . "/error/$lang-errors.php";
        $msg = isset($err[$code])?$err[$code]:$err[0];
        return $msg;
    }
}

/**
 * 获取uid
 */
if (!function_exists('getUid')) {
    function getUid()
    {
        $token = getInput('token');
        if(!$token){
            return false;
        }
        $uid = UserModel::where('token','=',$token)->value('id');
        if(!$uid){
            return 0;
        }
        return $uid;
    }
}

/**
 * 获取语言
 * @return string
 */
function getlang()
{
    $lang = Request::header('lang');

    if(!$lang || !in_array($lang,['zh','en','ru','ms','th','es'])){
        $lang = 'zh';
    }
    $lang = strtolower($lang);
    return $lang;
}


if (!function_exists('apps_lock')) {
    function apps_lock($return)
    {
        $iv_suffix = substr(md5(mt_rand()), 0, 6);
        $returnData = [
            'data' => apps_encrypt($return, $iv_suffix),
            'suffix' => $iv_suffix,
            // 'data1' => $return

        ];
        return $returnData;
    }
}


/**
 * 获取惟一订单号
 * @return string
 */
if (!function_exists('get_order_sn')) {
    function get_order_sn()
    {
        return date('YmdHis') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
}


/**
 * 解密
 * @return string
 */
if (!function_exists('apps_decrypt')) {
    function apps_decrypt($encrypted, $iv_suffix)
    {
        $app_asc_key = config('lock.app_asc_key');
        $app_asc_iv  = config('lock.app_asc_iv');

        $encrypted = base64_decode($encrypted);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $app_asc_key, OPENSSL_RAW_DATA, $app_asc_iv . $iv_suffix);
        return json_decode($decrypted, true);
    }
}


/**
 * 签名验证
 * @return string
 */
if (!function_exists('checkSign')) {
    function checkSign()
    {
        $input = getInput();
        if (!isset($input['timestamp']) || !isset($input['encode_sign'])){
            throw new BaseException(1001);
        };

        $linkString = build_link_string($input);
        $signKey    = config('lock.app_sign');
        $sign       = md5($linkString . $signKey);
        if (strtolower($sign) === strtolower($input['encode_sign'])) return true;
        else return false;

    }
}


/**
 *签名排序
 */
if (!function_exists('build_link_string')) {
    function build_link_string($params, $is_end = true)
    {
        //sign和空值不参与签名
        $paramsFilter = array();
        foreach ($params as $key => $val) {
            if ($key == 'encode_sign' || $val === '') {
                continue;
            } else {
                if (is_array($params[$key])) {
                    $paramsFilter[$key] = json_encode($params[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $paramsFilter[$key] = $params[$key];
                }
            }
        }
        ksort($paramsFilter);
        reset($paramsFilter);
        $query = '';
        foreach ($paramsFilter as $key => $val) {
            $query .= $key . '=' . $val . '&';
        }

        if ($params) {
            if (!$is_end) $query = substr($query, 0, strlen($query) - 1);
        }
        return $query;
    }
}
/*
 * 获取input
 */
if (!function_exists('getInput')) {
    function getInput($key = '')
    {
        if(config('lock.app_lock')){

            $data = input('post-data');
            if (empty($data)) {
                return false;
            }
            if (!Request::header('suffix')) return false;
            $iv_suffix = Request::header('suffix');
            $data = apps_decrypt($data, $iv_suffix);
            if ($key)
            {
                if (isset($data[$key])) return $data[$key];
                else return false;
            }
            return $data;

        }else{
            $data = input('post.');
            if ($key)
            {
                if (isset($data[$key])) return $data[$key];
                else return false;
            }
            return $data;
        }

    }
}


if (!function_exists('getInput1')) {
    function getInput1($key = '')
    {
        $data = input('post.');
        if ($key)
        {
            if (isset($data[$key])) return $data[$key];
            else return false;
        }
        return $data;
    }
}

/**
 * 加密
 * @return string
 */
if (!function_exists('apps_encrypt')) {
    function apps_encrypt($data, $iv_suffix)
    {
        $app_asc_key = config('lock.app_asc_key');
        $app_asc_iv  = config('lock.app_asc_iv');
        $encrypted = json_encode($data);
        $encrypted = openssl_encrypt($encrypted, 'aes-256-cbc', $app_asc_key, OPENSSL_RAW_DATA, $app_asc_iv . $iv_suffix);
        $encrypted = base64_encode($encrypted);

        return $encrypted;
    }
}

if (!function_exists('getage')) {
    function getage($birthday)
    {
        $age = strtotime($birthday);
        if ($age === false) {
            return 0;
        }
        list($y1, $m1, $d1) = explode("-", date("Y-m-d", $age));
        $now = strtotime("now");
        list($y2, $m2, $d2) = explode("-", date("Y-m-d", $now));
        $age = $y2 - $y1;
        if ((int)($m2 . $d2) < (int)($m1 . $d1))
            $age -= 1;
        return $age;
    }
}

if (!function_exists('httpPost')) {
    function httpPost($url,$param,$post_file=false){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);

        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }
}



function get_string($data){

    ksort($data);
    $string = "";
    foreach($data as $key => $value)
    {
        if(strlen($string) == 0)
            $string .= $key . "=" . $value;
        else
            $string .= "&" . $key . "=" . $value;
    }
    return $string;

}

function rsa_error($msg){
    die('RSA Error:' . $msg); //TODO
}

function rsa_readFile($file){
    $ret = false;
    if (!file_exists($file)){
        $this->_error("The file {$file} is not exists");
    } else {
        $ret = file_get_contents($file);

    }
    return $ret;
}



function wordTime($time) {

    $now = time();
    $int = $now - $time;

    if ($int <= 10){
        $str = sprintf('刚刚', $int);
    }elseif ($int < 60){
        $str = sprintf('%d秒前', $int);
    }elseif ($int < 3600){
        $str = sprintf('%d分钟前', floor($int / 60));
    }elseif ($int < 86400){
        $str = sprintf('%d小时前', floor($int / 3600));
    }elseif ($int < 2592000){
        $str = sprintf('%d天前', floor($int / 86400));
    }else{
        $str = date('Y-m-d H:i:s', $time);
    }
    return $str;
}


function save_log($msg, $path = '', $file_name = '')
{
    $action = $_SERVER["REQUEST_URI"];

    if ($path) $path = ROOT_PATH . '/runtime/log/' . $path; else $path = dirname(ROOT_PATH) . '/runtime/log';
    if (!$file_name) $file_name = date('Y-m-d') . '.log';

    if (!is_dir($path)) {
        mkdir($path, 0777,true);
    }
    if(is_array($msg)){
        $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
    }
    $filename = $path . '/' . $file_name;
    $content = date("Y-m-d H:i:s") . " $action\r\n" . $msg . "\r\n \r\n \r\n ";
    $res=file_put_contents($filename, $content, FILE_APPEND);
}


/**
 * 将s转换为时分秒
 */
if (!function_exists('secondsToHourMinute')) {
    function secondsToHourMinute($times){
        $result = "";
        if(empty($times) || !is_numeric($times)){
            return $result;
        }
        $hour = floor($times/3600);
        $minute = floor(($times-3600 * $hour)/60);
        $second = floor((($times-3600 * $hour) - 60 * $minute) % 60);
        if($hour<10){
            $hour = "0". $hour;
        }
        if($minute<10){
            $minute = "0". $minute;
        }
        if($second<10){
            $second = "0". $second;
        }
        $result = $hour .":". $minute .":". $second;
        return $result;
    }
}


/*
* 访问时用localhost访问的，读出来的是“::1”是正常情况。
* ：：1说明开启了ipv6支持,这是ipv6下的本地回环地址的表示。
* 使用ip地址访问或者关闭ipv6支持都可以不显示这个。
*/
// 获取真实IP地址
function get_client_ip() {
    static $realip = NULL;

    if ($realip !== NULL) {
        return $realip;
    }

    $keys = ['HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($keys as $key) {
        if (isset($_SERVER[$key])) {
            $realip = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                // 处理多个 IP 地址的情况，取第一个非空的有效 IP
                $realip = explode(',', $realip)[0];
            }
            break;
        } elseif (getenv($key)) {
            $realip = getenv($key);
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                // 处理多个 IP 地址的情况，取第一个非空的有效 IP
                $realip = explode(',', $realip)[0];
            }
            break;
        }
    }

    if (empty($realip)) {
        $realip = '0.0.0.0';
    }

    return $realip;
}
/**
 * 获取站点
 */
if (!function_exists('getSite')) {
    function getSite(){
        if(empty(getinput('site')) || !in_array(getinput('site'),[1,2,3,4])){
            $site = 1;
        }else{
            $site = getinput('site');
        }
        return $site;
    }
}


/**
 * 获取站点
 */
if (!function_exists('device')) {
    function getDevice(){
        if(empty(getinput('device')) || !in_array(getinput('device'),[1,2])){
            $device = 1;
        }else{
            $device = getinput('device');
        }
        return $device;
    }
}



if (!function_exists('formatNumber')) {
    function formatNumber($num)
    {
        if ($num >= 10000) {
            return round($num / 10000) . 'W';
        } else {
            return $num;
        }
    }
}
