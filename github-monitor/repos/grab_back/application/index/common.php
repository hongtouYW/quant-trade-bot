<?php

/**
 * 显示所有的菜单权限
 * @param $data
 * @param int $pid
 * @param int $level
 * @param int $index
 * @return array
 */
function to_tree($data, $pid = 0,$level=0,$index=0)
{
    $arr = array();
    foreach ($data as $k => $v) {
        if ($pid == $v['parent_id']) {
            $v['href_name'] = $v['url'];
            $v['name'] = $v['name'];
            $v['level'] = $level + 1;
            $v['spread'] = false;
            if($index!=0){
                if($index!=$v['level']){
                    $v['children'] = to_tree($data, $v['id'], $v['level'],$index);
                    if (empty($v['children'])) {
                        unset($v['children']);
                    }
                }
            }else{
                $v['children'] = to_tree($data, $v['id'], $v['level'],$index);
                if (empty($v['children'])) {
                    unset($v['children']);
                }
            }
            array_push($arr, $v);
        }
    }
    return $arr;
}
/*
 * 获取管理员是否是超级管理员
 *
 */
function is_role(){
    $admin_id=session('admin_id');
    $adminInfo=\app\index\model\Admin::where(array("id"=>$admin_id))->find();
    if ($adminInfo["role_id"]==1){
        return 1;
    }else{
        return 2;
    }
}

/**
 * 记录操作日志
 * @param  [type] $uid         [用户id]
 * @param  [type] $username    [用户名]
 * @param  [type] $description [描述]
 * @param  [type] $status      [状态]
 * @return [type]              [description]
 * @return [type] $type        0错误日志  -1正确
 */


/*
 * php获取客户端浏览器信息  和版本
 */
function get_broswer_type(){
    $sys = $_SERVER['HTTP_USER_AGENT'];  //获取用户代理字符串
    if (stripos($sys, "Firefox/") > 0) {
        preg_match("/Firefox\/([^;)]+)+/i", $sys, $b);
        $exp[0] = "Firefox";
        $exp[1] = $b[1];  //获取火狐浏览器的版本号
    } elseif (stripos($sys, "Maxthon") > 0) {
        preg_match("/Maxthon\/([\d\.]+)/", $sys, $aoyou);
        $exp[0] = "傲游";
        $exp[1] = $aoyou[1];
    } elseif (stripos($sys, "MSIE") > 0) {
        preg_match("/MSIE\s+([^;)]+)+/i", $sys, $ie);
        $exp[0] = "IE";
        $exp[1] = $ie[1];  //获取IE的版本号
    } elseif (stripos($sys, "OPR") > 0) {
        preg_match("/OPR\/([\d\.]+)/", $sys, $opera);
        $exp[0] = "Opera";
        $exp[1] = $opera[1];
    } elseif(stripos($sys, "Edge") > 0) {
        //win10 Edge浏览器 添加了chrome内核标记 在判断Chrome之前匹配
        preg_match("/Edge\/([\d\.]+)/", $sys, $Edge);
        $exp[0] = "Edge";
        $exp[1] = $Edge[1];
    } elseif (stripos($sys, "Chrome") > 0) {
        preg_match("/Chrome\/([\d\.]+)/", $sys, $google);
        $exp[0] = "Chrome";
        $exp[1] = $google[1];  //获取google chrome的版本号
    } elseif(stripos($sys,'rv:')>0 && stripos($sys,'Gecko')>0){
        preg_match("/rv:([\d\.]+)/", $sys, $IE);
        $exp[0] = "IE";
        $exp[1] = $IE[1];
    }else {
        $exp[0] = "未知浏览器";
        return $exp[0];
    }
    return $exp[0].'('.$exp[1].')';
}
/*
 * php获取客户端浏览器
 */
function get_broswer(){
    $sys = $_SERVER['HTTP_USER_AGENT'];  //获取用户代理字符串
    if (stripos($sys, "Firefox/") > 0) {
        $exp[0] = "Firefox";
    } elseif (stripos($sys, "Maxthon") > 0) {
        $exp[0] = "傲游";
    } elseif (stripos($sys, "MSIE") > 0) {
        $exp[0] = "IE";
    } elseif (stripos($sys, "OPR") > 0) {
        $exp[0] = "Opera";
    } elseif(stripos($sys, "Edge") > 0) {
        $exp[0] = "Edge";
    } elseif (stripos($sys, "Chrome") > 0) {
        $exp[0] = "Chrome";
    } elseif(stripos($sys,'rv:')>0 && stripos($sys,'Gecko')>0){
        $exp[0] = "IE";
    }else {
        $exp[0] = "未知浏览器";
    }
    return $exp[0];
}

/*
 * PHP获取客户端操作系统信息
 */
function get_os(){
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $os = false;
    if (preg_match('/win/i', $agent) && strpos($agent, '95'))
    {
        $os = 'Windows 95';
    }
    else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90'))
    {
        $os = 'Windows ME';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent))
    {
        $os = 'Windows 98';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent))
    {
        $os = 'Windows Vista';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent))
    {
        $os = 'Windows 7';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent))
    {
        $os = 'Windows 8';
    }else if(preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent))
    {
        $os = 'Windows 10';#添加win10判断
    }else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent))
    {
        $os = 'Windows XP';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent))
    {
        $os = 'Windows 2000';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent))
    {
        $os = 'Windows NT';
    }
    else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent))
    {
        $os = 'Windows 32';
    }
    else if (preg_match('/linux/i', $agent))
    {
        $os = 'Linux';
    }
    else if (preg_match('/unix/i', $agent))
    {
        $os = 'Unix';
    }
    else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent))
    {
        $os = 'SunOS';
    }
    else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent))
    {
        $os = 'IBM OS/2';
    }
    else if (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent))
    {
        $os = 'Macintosh';
    }
    else if (preg_match('/PowerPC/i', $agent))
    {
        $os = 'PowerPC';
    }
    else if (preg_match('/AIX/i', $agent))
    {
        $os = 'AIX';
    }
    else if (preg_match('/HPUX/i', $agent))
    {
        $os = 'HPUX';
    }
    else if (preg_match('/NetBSD/i', $agent))
    {
        $os = 'NetBSD';
    }
    else if (preg_match('/BSD/i', $agent))
    {
        $os = 'BSD';
    }
    else if (preg_match('/OSF1/i', $agent))
    {
        $os = 'OSF1';
    }
    else if (preg_match('/IRIX/i', $agent))
    {
        $os = 'IRIX';
    }
    else if (preg_match('/FreeBSD/i', $agent))
    {
        $os = 'FreeBSD';
    }
    else if (preg_match('/teleport/i', $agent))
    {
        $os = 'teleport';
    }
    else if (preg_match('/flashget/i', $agent))
    {
        $os = 'flashget';
    }
    else if (preg_match('/webzip/i', $agent))
    {
        $os = 'webzip';
    }
    else if (preg_match('/offline/i', $agent))
    {
        $os = 'offline';
    }
    else
    {
        $os = '未知操作系统';
//        $os = get_device_type();
    }
    return $os;
}


/*
 *  生成二维码 （不带logo图片的）
 * //$text  文本的内容
 * //$type  类型
 */
function qc_code($text,$type){
    //二维码图片保存路径
    $pathname = ROOT_PATH . 'public' . DS . 'upload'. DS . 'qrcode'. DS . $type;
    if(!is_dir($pathname)) { //若目录不存在则创建之
        mkdir($pathname,0777,true);
    }
    vendor("phpqrcode.phpqrcode");

//二维码图片保存路径(若不生成文件则设置为false)
    $filename =$pathname."/qrcode_" . time() . ".png";
//    $filename ="/upload/qrcode/$type/qrcode_" . time() . ".png";
//二维码容错率，默认L
    $level = "L";
//二维码图片每个黑点的像素，默认4
    $size = '10';
//二维码边框的间距，默认2
    $padding = 2;
//保存二维码图片并显示出来，$filename必须传递文件路径
    $saveandprint = true;

//生成二维码图片
    \PHPQRCode\QRcode::png($text,$filename,$level,$size,$padding,$saveandprint);

//二维码logo
    $QR = imagecreatefromstring(file_get_contents($filename));

    imagepng($QR,$filename);
    return $filename;

}


function sendMessage($to_uid,$msg){

    $data = [
        'to_uid'=>$to_uid,
        'content'=>$msg,
        'insert_time'=>time()
    ];
    \app\index\model\Message::insert($data);

}

