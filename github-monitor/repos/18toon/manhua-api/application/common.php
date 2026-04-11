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

use app\index\model\Config;
use app\index\model\Ticai;
use think\facade\Request;
use think\facade\Env;
use app\index\model\User as UserModel;
use app\lib\exception\BaseException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use think\cache\driver\Redis;

/**
 * 删除图片
 */
if (!function_exists('delImage')) {
    function delImage($path)
    {
        $img_path = Env::get('root_path') . 'public/';
        if (!empty($path) && file_exists($img_path . $path)) { //检查图片文件是否存在
            unlink($img_path . $path);
        }
    }
}

/**
 * 生成随机码
 */

if (!function_exists('getRandChar')) {
    function getRandChar($length, $randomChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijkomnopqrstuvwxyz')
    {
        $randomCode = "";
        for ($i = 0; $i < $length; $i++) {
            $randomCode .= $randomChars[mt_rand(0, strlen($randomChars) - 1)];
        }
        return $randomCode;
    }
}



/**
 * 生成随机昵称
 */

if (!function_exists('getNickname')) {
    function getNickname($length, $last_id)
    {
        $randomChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijkomnopqrstuvwxyz';
        $randomCode = "";
        for ($i = 0; $i < $length; $i++) {
            $randomCode .= $randomChars[mt_rand(0, strlen($randomChars) - 1)];
        }
        return "用户" . strtolower($randomCode) . ($last_id + 1);
    }
}






/**
 *接口统一返回
 */
if (!function_exists('show')) {
    function show($code = '', $data = [], $httpCode = 200)
    {
        $result = [
            'code' => intval($code),
            'msg' => getMsg($code),
            'timestamp' => time()
        ];
        $app_status = config('lock.app_lock');
        $result['crypt'] = $app_status;
        if (!empty($data) || $data == 0) {
            $result['data'] = $data;
        }
        if ($app_status) {
            $result = apps_lock($result);
        }
        return json($result);
    }
}

/**
 * 接口统一返回 (无加密/明文版本)
 */
if (!function_exists('showPlain')) {
    function showPlain($code = '', $data = [], $httpCode = 200)
    {
        $result = [
            'code' => intval($code),
            'msg' => getMsg($code),
            'timestamp' => time()
        ];
        
        $result['crypt'] = false; 
        
        if (!empty($data) || $data == 0) {
            $result['data'] = $data;
        }
        
        return json($result, $httpCode); 
    }
}

if (!function_exists('getMsg')) {
    function getMsg($code)
    {
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $err = require_once Env::get('app_path') . "/error/$lang-errors.php";
        $msg = isset($err[$code]) ? $err[$code] : $err[0];
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
        if (!$token) {
            return false;
        }
        $uid = UserModel::where('token', '=', $token)->value('id');
        if (!$uid) {
            return false;
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

    if (!$lang || !in_array($lang, ['zh', 'en'])) {
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
            'time' => date("Y-m-d H:i:s"),
            'suffix' => $iv_suffix,
            'data' => apps_encrypt($return, $iv_suffix),
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
        $app_asc_iv = config('lock.app_asc_iv');

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
        if (!isset($input['timestamp']) || !isset($input['encode_sign'])) {
            throw new BaseException(1001);
        };

        $linkString = build_link_string($input);
        $signKey = config('lock.app_sign');
        $sign = md5($linkString . $signKey);
        if (strtolower($sign) === strtolower($input['encode_sign']))
            return true;
        else
            return false;
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
            if (!$is_end)
                $query = substr($query, 0, strlen($query) - 1);
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
        if (config('lock.app_lock')) {

            $data = input('post-data');
            if (empty($data)) {
                return false;
            }
            if (!Request::header('suffix'))
                return false;
            $iv_suffix = Request::header('suffix');
            $data = apps_decrypt($data, $iv_suffix);
            if ($key) {
                if (isset($data[$key]))
                    return $data[$key];
                else
                    return false;
            }
            return $data;
        } else {
            $data = input('post.');
            if ($key) {
                if (isset($data[$key]))
                    return $data[$key];
                else
                    return false;
            }
            return $data;
        }
    }
}


if (!function_exists('getInput1')) {
    function getInput1($key = '')
    {
        $data = input('post.');
        if ($key) {
            if (isset($data[$key]))
                return $data[$key];
            else
                return false;
        }
        return $data;
    }
}

/**
 * 获取客户端ip
 */
if (!function_exists('getRealIP')) {
    function getRealIP()
    {
        $forwarded = request()->header("x-forwarded-for");
        if ($forwarded) {
            $ip = explode(',', $forwarded)[0];
        } else {
            $ip = request()->ip();
        }
        return $ip;
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
        $app_asc_iv = config('lock.app_asc_iv');
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
        if ((int) ($m2 . $d2) < (int) ($m1 . $d1))
            $age -= 1;
        return $age;
    }
}

if (!function_exists('httpPost')) {
    function httpPost($url, $param, $post_file = false)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);

        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }
}

if (!function_exists('get_string')) {
    function get_string($data)
    {

        ksort($data);
        $string = "";
        foreach ($data as $key => $value) {
            if (strlen($string) == 0)
                $string .= $key . "=" . $value;
            else
                $string .= "&" . $key . "=" . $value;
        }
        return $string;
    }
}



if (!function_exists('rsa_error')) {
    function rsa_error($msg)
    {
        die('RSA Error:' . $msg); //TODO
    }
}
if (!function_exists('rsa_readFile')) {
    function rsa_readFile($file)
    {
        $ret = false;
        if (!file_exists($file)) {
            $this->_error("The file {$file} is not exists");
        } else {
            $ret = file_get_contents($file);
        }
        return $ret;
    }
}


function wordTime($time)
{

    $now = time();
    $int = $now - $time;

    if ($int <= 10) {
        $str = sprintf('刚刚', $int);
    } elseif ($int < 60) {
        $str = sprintf('%d秒前', $int);
    } elseif ($int < 3600) {
        $str = sprintf('%d分钟前', floor($int / 60));
    } elseif ($int < 86400) {
        $str = sprintf('%d小时前', floor($int / 3600));
    } elseif ($int < 2592000) {
        $str = sprintf('%d天前', floor($int / 86400));
    } else {
        $str = date('Y-m-d H:i:s', $time);
    }
    return $str;
}

if (!function_exists('save_log')) {
    function save_log($msg, $path = '', $file_name = '')
    {
        $action = $_SERVER["REQUEST_URI"];
        $rootPath = app()->getRootPath();

        if ($path)
            $path = $rootPath . '/runtime/log/' . $path;
        else
            $path = dirname($rootPath) . '/runtime/log';
        if (!$file_name)
            $file_name = date('Y-m-d') . '.log';

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $filename = $path . '/' . $file_name;
        $content = date("Y-m-d H:i:s") . " $action\r\n" . $msg . "\r\n \r\n \r\n ";
        $res = file_put_contents($filename, $content, FILE_APPEND);
    }
}

if (!function_exists('payment_log')) {
    function payment_log($msg, $path = '', $file_name = '')
    {
        $action = $_SERVER["REQUEST_URI"] ?? '';
        $rootPath = app()->getRootPath();
        $basePath = $rootPath . '/runtime/payment_log/';

        if ($path) {
            $path = $basePath . $path;
        } else {
            $path = $basePath;
        }

        if (!$file_name) {
            $file_name = date('Y-m-d') . '.log';
        }

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $filename = $path . '/' . $file_name;
        $content = date("Y-m-d H:i:s") . " $action\r\n" . $msg . "\r\n \r\n \r\n ";
        file_put_contents($filename, $content, FILE_APPEND);
    }
}

/**
 * 将s转换为时分秒
 */
if (!function_exists('secondsToHourMinute')) {
    function secondsToHourMinute($times)
    {
        $result = "";
        if (empty($times) || !is_numeric($times)) {
            return $result;
        }
        $hour = floor($times / 3600);
        $minute = floor(($times - 3600 * $hour) / 60);
        $second = floor((($times - 3600 * $hour) - 60 * $minute) % 60);
        if ($hour < 10) {
            $hour = "0" . $hour;
        }
        if ($minute < 10) {
            $minute = "0" . $minute;
        }
        if ($second < 10) {
            $second = "0" . $second;
        }
        $result = $hour . ":" . $minute . ":" . $second;
        return $result;
    }
}

function is_vip($uid)
{
    $viptime = UserModel::where('id', '=', $uid)->value('viptime');
    return $viptime > time() ? 1 : 0;
}

/**
 * 发送邮箱验证码
 */
function sendMail($toEmail, $code)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = Env::get('mail.host', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = Env::get('mail.username');
        $mail->Password = Env::get('mail.password');
        $mail->SMTPSecure = Env::get('mail.encryption', 'tls');
        $mail->Port = Env::get('mail.port', 587);
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            Env::get('mail.from', Env::get('username')),
            Env::get('mail.from_name', 'Website')
        );
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = '您的验证码';
        $mail->Body = "您的验证码是：<b>{$code}</b>，有效期5分钟。";

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo $mail->ErrorInfo;
        error_log("邮件发送失败: {$mail->ErrorInfo}");
        return false;
    }
}

if (!function_exists('injectTicaiName')) {
    function injectTicaiName(array $list, string $lang): array
    {
        if (empty($list))
            return $list;

        // 收集所有 ticai_id
        $ticaiIds = array_unique(array_column($list, 'ticai_id'));

        if (empty($ticaiIds))
            return $list;

        // 构建查询
        $query = Ticai::alias('c')
            ->field('c.id')
            ->whereIn('c.id', $ticaiIds);

        Ticai::withTranslation($query, 'c', $lang, ['name'], 'qiswl_ticai_trans', 'ticai_id');
        $ticaiList = $query->select();

        $ticaiMap = [];
        foreach ($ticaiList as $ticai) {
            $ticaiMap[$ticai['id']] = $ticai['name'] ?? '';
        }

        // 注入 ticai_name
        foreach ($list as &$item) {
            $item['ticai_name'] = $ticaiMap[$item['ticai_id']] ?? '';
        }

        return $list;
    }
}

if (!function_exists('rateLimit')) {
    function rateLimit($key, $ttl = 5)
    {
        $redis = new Redis();

        if ($redis->exists($key)) {
            return true;
        } else {
            $redis->setex($key, $ttl, 1);
            return false;
        }
    }
}

if (!function_exists('filterSameImageFromImagelist')) {
    function filterSameImageFromImagelist($imagelist, $targetImageUrls)
    {
        $imgDomain = Config::get('IMAGE_HOST');

        if (empty($imagelist) || empty($targetImageUrls)) {
            return $imagelist;
        }

        // 统一处理目标图片 hash 列表
        $targetHashes = [];

        $targetImageUrls = is_array($targetImageUrls) ? $targetImageUrls : [$targetImageUrls];

        foreach ($targetImageUrls as $url) {
            // 拼接完整路径（如果不是 http 开头）
            $fullUrl = (stripos($url, 'http') === 0) ? $url : rtrim($imgDomain, '/') . '/' . ltrim($url, '/');

            $hash = @md5_file($fullUrl);
            if ($hash !== false) {
                $targetHashes[] = $hash;
            }
        }

        // 如果目标 hash 都失败，返回原始列表
        if (empty($targetHashes)) {
            return $imagelist;
        }

        $images = is_array($imagelist) ? $imagelist : explode(',', $imagelist);
        $filtered = [];

        foreach ($images as $img) {
            $img = trim($img);
            if (empty($img))
                continue;

            $imgUrl = (stripos($img, 'http') === 0) ? $img : rtrim($imgDomain, '/') . '/' . ltrim($img, '/');

            $imgHash = @md5_file($imgUrl);
            if ($imgHash === false || !in_array($imgHash, $targetHashes)) {
                $filtered[] = $img;
            }
        }

        return implode(',', $filtered);
    }
}
