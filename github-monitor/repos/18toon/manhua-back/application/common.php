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

error_reporting(E_ALL ^ E_NOTICE);

use think\Db;

//公共函数
/**
 * [pswCrypt description]密码加密
 * @param  [type] $psw [description]
 * @return [type]      [description]
 */
function pswCrypt($psw)
{
    $psw = md5($psw);
    $salt = substr($psw, 0, 4);
    $psw = crypt($psw, $salt);
    return $psw;
}


/**
 * [getActionUrl description]获取当前url
 * @return [type] [description]
 */
function getActionUrl()
{
    $module = strtolower(request()->module());
    $controller = strtolower(request()->controller());
    $action = strtolower(request()->action());
    return $module . '/' . $controller . '/' . $action;
}

/**
 * 数组层级缩进转换
 * @param array $array
 * @param int   $pid
 * @param int   $level
 * @return array
 */
function tree($array, $pid = 0, $level = 1)
{
    static $list = [];
    foreach ($array as $v) {
        if ($v['parent_id'] == $pid) {
            $v['level'] = $level;
            $list[] = $v;
            $this->tree($array, $v['id'], $level + 1);
        }
    }
    return $list;
}

/**
 * 构建层级（树状）数组
 * @param array  $array 要进行处理的一维数组，经过该函数处理后，该数组自动转为树状数组
 * @param string $pid 父级ID的字段名
 * @param string $child_key_name 子元素键名
 * @return array|bool
 */
function array2tree(&$array, $pid = 'pid', $child_key_name = 'children')
{
    $counter = $this->array_children_count($array, $pid);
    if ($counter[0] == 0) {
        return false;
    }
    $tree = [];
    while (isset($counter[0]) && $counter[0] > 0) {
        $temp = array_shift($array);
        if (isset($counter[$temp['id']]) && $counter[$temp['id']] > 0) {
            array_push($array, $temp);
        } else {
            if ($temp[$pid] == 0) {
                $tree[] = $temp;
            } else {
                $array = $this->array_child_append($array, $temp[$pid], $temp, $child_key_name);
            }
        }
        $counter = $this->array_children_count($array, $pid);
    }

    return $tree;
}

/**
 * 子元素计数器
 * @param $array
 * @param $pid
 * @return array
 */
function array_children_count($array, $pid)
{
    $counter = [];
    foreach ($array as $item) {
        $count = isset($counter[$item[$pid]]) ? $counter[$item[$pid]] : 0;
        $count++;
        $counter[$item[$pid]] = $count;
    }

    return $counter;
}

/**
 * 把元素插入到对应的父元素$child_key_name字段
 * @param        $parent
 * @param        $pid
 * @param        $child
 * @param string $child_key_name 子元素键名
 * @return mixed
 */
function array_child_append($parent, $pid, $child, $child_key_name)
{
    foreach ($parent as &$item) {
        if ($item['id'] == $pid) {
            if (!isset($item[$child_key_name]))
                $item[$child_key_name] = [];
            $item[$child_key_name][] = $child;
        }
    }

    return $parent;
}


/**
 * [log description]打印日志
 * @param  [type] $name  [description]
 * @param  [type] $value [description]
 * @param  [type] $file  [description]
 * @param  [type] $line  [description]
 * @return [type]        [description]
 */
function logs($name, $value, $file = __FILE__, $line = __LINE__)
{
    $value = date('Y-m-d H:i:s') . " " . $value;
    return app_log(date('Ymd') . $name, $value, "", $line);
}

/**
 * [app_log description]日志
 * @param  [type] $name  [description]
 * @param  [type] $value [description]
 * @param  [type] $file  [description]
 * @param  [type] $line  [description]
 * @return [type]        [description]
 */
function app_log($name, $value, $file = __FILE__, $line = __LINE__)
{
    $value = "<?exit;?" . ">$file\t$line\t" . $value . "\n";
    if (!is_dir(ROOT_PATH . 'cache')) { //当路径不穿在
        mkdir(ROOT_PATH . 'cache', 0777);
        chmod(ROOT_PATH . 'cache', 0777);
    }
    file_put_contents(ROOT_PATH . 'cache/log.' . $name . '.php', $value, FILE_APPEND);
}

/**
 * 循环删除目录和文件
 * @param string $dir_name
 * @return bool
 */
if (!function_exists('delete_dir_file')) {
    /**
     * 循环删除目录和文件
     * @param string $dir_name
     * @return bool
     */
    function delete_dir_file($dir_name)
    {
        $result = false;
        if (is_dir($dir_name)) {
            if ($handle = opendir($dir_name)) {
                while (false !== ($item = readdir($handle))) {
                    if ($item != '.' && $item != '..') {
                        if (is_dir($dir_name . DIRECTORY_SEPARATOR . $item)) {
                            delete_dir_file($dir_name . DIRECTORY_SEPARATOR . $item);
                        } else {
                            unlink($dir_name . DIRECTORY_SEPARATOR . $item);
                        }
                    }
                }
                closedir($handle);
                if (rmdir($dir_name)) {
                    $result = true;
                }
            }
        }
        return $result;
    }
}

/*
 * 检查图片是不是bases64编码的
 */
function is_image_base64($base64)
{
    /*if($base64==base64_encode(base64_decode($base64))){
        return true;
    }else{
        return false;
    }*/
    if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
        return true;
    } else {
        return false;
    }
}


/**
 * 记录日志
 */
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
 * 发送post请求
 */
if (!function_exists('httpPost')) {
    /**
     * PHP发送Json对象数据
     * @param $url 请求url
     * @param $data 发送的json字符串/数组
     * @return array
     */
    function httpPost($url, $data = NULL)
    {

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!$data) {
            return 'data is null';
        }
        if (is_array($data)) {
            $data = json_encode($data);
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:' . strlen($data),
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        if ($errorno) {
            return $errorno;
        }
        curl_close($curl);
        return $res;
    }
}

if (!function_exists('get_actor_translation')) {
    function get_actor_translation($trans_list, $lang_code)
    {
        foreach ($trans_list as $trans) {
            if ($trans['lang_code'] === $lang_code) {
                return $trans['name'];
            }
        }
        return '';
    }
}

if (!function_exists('sendTelegramMessage')) {
    function sendTelegramMessage($message, $threadId = null, $parseMode = 'Markdown')
    {
        // 建议放在配置文件中
        $botToken = '8308698753:AAGwKdBUeaj-G6N1FcJNtCMEfV098CWC9Oc';
        $chatId = '-1002861391029';

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ];

            if (!is_null($threadId)) {
                $params['message_thread_id'] = $threadId;
            }

            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $client->post($url, [
                'form_params' => $params,
            ]);
        } catch (\Exception $e) {
            // save_log("发送 Telegram 报警失败: " . $e->getMessage(), "telegram");
        }
    }
}

if (!function_exists('insertOrUpdate')) {
    function insertOrUpdate(string $table, array $where, array $data)
    {
        $exists = Db::table($table)->where($where)->find();

        if ($exists) {
            Db::table($table)->where($where)->update($data);
        } else {
            Db::table($table)->insert(array_merge($where, $data));
        }
    }
}

if (!function_exists('insertOrUpdateOrigin')) {
    function insertOrUpdateOrigin(string $table, array $where, array $data)
    {
        $exists = Db::table($table)->where($where)->find();

        if ($exists) {
            Db::table($table)->where($where)->update($data);
        } else {
            Db::table($table)->insert(array_merge($where, $data));
        }
    }
}

if (!function_exists('syncSubTable')) {
    function syncSubTable(string $table, int $manhuaId, array $items, array $fields)
    {
        $db = Db::connect('mh_db'); // 指定连接

        // 获取已有 ID
        $existingIds = $db->name($table)->where('manhua_id', $manhuaId)->column('id');
        $keepIds = [];
        $idMap = [];

        foreach ($items as $index => $item) {
            $data = ['manhua_id' => $manhuaId, 'update_time' => time()];
            foreach ($fields as $field) {
                $value = $item[$field] ?? '';
                $data[$field] = $value;
            }

            if (!empty($item['id'])) {
                // 更新
                $keepIds[] = $item['id'];
                $db->name($table)->where('id', $item['id'])->update($data);
                $idMap[$item['id']] = $item['id'];
            } else {
                // 新增
                $data['create_time'] = time();
                $newId = $db->name($table)->insertGetId($data);
                $keepIds[] = $newId;
                $idMap[$index] = $newId;
            }
        }

        // 删除未提交的旧数据
        if (!empty($existingIds)) {
            $toDelete = array_diff($existingIds, $keepIds);
            if (!empty($toDelete)) {
                $db->name($table)->where('manhua_id', $manhuaId)->whereIn('id', $toDelete)->delete();
            }
        }

        return $idMap;
    }
}

