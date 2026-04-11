<?php

namespace app\index\controller;

use app\index\model\Chapter;
use app\index\model\Feedback;
use app\index\model\History;
use app\index\model\Manhua;
use app\index\model\Record;
use app\index\model\Order;
use app\index\model\Token;
use app\index\model\User as UserModel;
use app\index\model\Config as ConfigModel;
use app\index\model\Code as CodeModel;
use app\index\model\StatisticChannel as StatisticChannelModel;
use app\lib\exception\BaseException;
use think\Db;
use think\facade\Request;
use think\facade\Validate;
use think\facade\Log;
use app\service\ChannelStatsService;

class User extends Base
{
    /**
     * Notes:注册
     *
     * DateTime: 2022/4/21 15:39
     */
    public function register()
    {

        $username = trim(getInput('username'));
        $password = trim(getInput('password'));
        $repassword = trim(getInput('repassword'));
        $channel_name = trim(getInput('channel_name')) ?? null;

        $rule = [
            'username' => 'require|alphaNum|length:6,20',
            'password' => 'require|alphaNum|length:6,20|confirm:repassword',
            'channel_name' => 'regex:^[A-Za-z0-9_-]+$'
        ];

        Request::except('password_confirm', 'post');

        $msg = [
            'username.require' => 2001,
            'username.alphaNum' => 2002,
            'username.length' => 2020,
            'password.require' => 2003,
            'password.alphaNum' => 2004,
            'password.length' => 2005,
            'password.confirm' => 2006
        ];

        $data = [
            'username' => $username,
            'password' => $password,
            'repassword' => $repassword
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);

        if (!$result) {
            return show($validate->getError());
        }
        $is_name = UserModel::where('username', '=', $username)->value('id');
        if ($is_name) {
            return show(2007);
        }
        $x = 1;
        do {
            $code = strtolower(getRandChar(6));
            $user = UserModel::where('code', '=', $code)->value('id');
            $x++;
        } while ($user != null && $x < 5); //检查5次避免重复，通常只跑一次

        if ($x == 5)
            throw new BaseException(999);
        $time = time();
        $ip = Request::ip();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $user_agent = md5($http_user_agent . $ip);
        $is_agent = UserModel::where('useragent', '=', $user_agent)->value('id');

        if ($is_agent) {
            $score = 0;
        } else {
            $score = ConfigModel::get('REG_SCORE');
        }

        $channel_id_check = null;
        $channel_expire_time = 0;
        if (!empty($channel_name)) {
            $channel = StatisticChannelModel::getByName($channel_name);
            if ($channel && ChannelStatsService::setChannelByName($channel_name)) {
                $channel_id_check = $channel->id;
                $channel_expire_time = time() + (24 * 3600);
            } else {
                Log::warning("[ChannelStats] Channel not found for channel_name: {$channel_name}");
            }
        }

        $data = [
            'username' => $username,
            'password' => $password,
            'useragent' => $user_agent,
            'register_time' => $time,
            'last_time' => $time,
            'login_ip' => $ip,
            'register_ip' => $ip,
            'code' => $code,
            'token' => Token::generateToken(),
            'token_val' => $time + config('lock.app_token_val'),
            'type' => 1,
            'score' => $score,
            'channel_id' => $channel_id_check,
            'channel_expire_time' => $channel_expire_time,
        ];
        Db::startTrans();
        try {
            $add_id = UserModel::insertGetId($data);
            if ($channel_id_check !== null) {
                ChannelStatsService::recordRegister($channel_id_check);
            }
        } catch (\Exception $e) {
            Db::rollback();
            throw new BaseException(999);
        }
        Db::commit();
        $userInfo = UserModel::getUserInfo($add_id);
        return show(1, $userInfo);
    }


    /**
     * Notes:用户名登陆
     *
     * DateTime: 2022/4/21 16:24
     */
    public function login()
    {
        $username = trim(getInput('username'));
        $password = trim(getInput('password'));
        $rule = [
            'username' => 'require|alphaNum|length:6,20',
            'password' => 'require|alphaNum|length:6,20',
        ];
        $msg = [
            'username.require' => 2001,
            'username.alphaNum' => 2002,
            'username.length' => 2020,
            'password.require' => 2003,
            'password.alphaNum' => 2004,
            'password.length' => 2005,
        ];

        $data = [
            'username' => $username,
            'password' => $password,
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);

        if (!$result) {
            return show($validate->getError());
        }
        $user = UserModel::field('id,status,password,code,channel_id')->where('username', '=', $username)->find();
        if (!$user) {
            return show(2008);
        }
        if ($user['password'] != $password) {
            return show(2009);
        }
        if ($user['status'] == 0) {
            return show(2010);
        }
        $time = time();
        $ip = Request::ip();
        $isFirstLoginToday = date('Y-m-d', $user['last_time'] ?? 0) !== date('Y-m-d', $time);
        $data = [
            'token' => Token::generateToken(),
            'token_val' => $time + config('lock.app_token_val'),
            'last_time' => $time,
            'login_ip' => $ip,
            'login_nums' => Db::raw('login_nums+1')
        ];
        if (empty($user['code'])) {
            $data['code'] = strtolower(getRandChar(6));
        }
        $is_up = UserModel::where('id', '=', $user['id'])->update($data);
        if (!$is_up) {
            throw new BaseException(999);
        }
        $channelId = ChannelStatsService::getValidChannelIdByUser($user['id']);
        if (!empty($channelId) && $isFirstLoginToday) {
            ChannelStatsService::recordLogin($channelId);
        }
        $userInfo = UserModel::getUserInfo($user['id']);
        return show(1, $userInfo);
    }

    /**
     * Notes:退出
     *
     * DateTime: 2022/5/19 16:00
     */
    public function logout()
    {
        $uid = Token::getCurrentUid();
        $data = [
            'token' => Token::generateToken(),
            'token_val' => time() - 1
        ];
        $is_up = UserModel::where('id', '=', $uid)->update($data);
        if (!$is_up) {
            throw new BaseException(999);
        }
        return show(1);
    }


    /**
     * Notes:获取用户详情
     *
     * DateTime: 2022/5/5 19:32
     */
    public function info()
    {

        $uid = Token::getCurrentUid();
        $info = UserModel::getUserInfo($uid);
        return show(1, $info);
    }


    /**
     * Notes:反馈
     *
     * DateTime: 2023/8/1 1:31
     */
    public function feedback()
    {
        $ip = getRealIP();
        $uid = NULL;
        $token = !empty(getInput('token')) ? getInput('token') : '';

        if ($token && $token !== 'undefined') {
            try {
                $uid = Token::getCurrentUid();
            } catch (\Exception $e) {
                $uid = NULL;
            }
        }

        $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        $count = Feedback::where('add_time', '>=', $beginToday)
            ->where(function ($query) use ($uid, $ip) {
                if ($uid !== NULL) {
                    $query->where('member_id', '=', $uid);
                } else {
                    $query->where('ip', '=', $ip);
                }
            })
            ->count();

        if ($count >= 5) {
            return show(2013);
        }

        $contact = trim(getInput('contact'));
        $content = trim(getInput('content'));
        $satisfaction = (int) getInput('satisfaction');

        $rule = [
            'contact' => 'max:100',
            'content' => 'require|max:500',
            'satisfaction' => 'require|in:1,2,3',
        ];
        $msg = [
            'content.require' => 2015,
            'contact.max' => 2016,
            'content.max' => 2017,
            'satisfaction.require' => 2030,
            'satisfaction.in' => 2031,
        ];

        $validateData = [
            'contact' => $contact,
            'content' => $content,
            'satisfaction' => $satisfaction,
        ];

        $validate = Validate::make($rule)->message($msg);

        if (!$validate->check($validateData)) {
            return show($validate->getError());
        }

        $insertData = [
            'satisfaction' => $satisfaction,
            'member_id' => $uid,
            'contact' => $contact,
            'content' => $content,
            'add_time' => time(),
            'ip' => $ip
        ];

        $res = Feedback::insert($insertData);
        if (!$res) {
            return show(0);
        }
        return show(1);
    }

    /**
     * Notes:我的反馈
     *
     * DateTime: 2023/9/3 22:19
     */
    public static function myFeedback()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lists = Feedback::lists($uid, $page, $limit);
        return show(1, $lists);
    }


    /**
     * Notes:修改密码
     *
     * DateTime: 2023/9/3 22:19
     */
    public function changepassword()
    {
        $uid = Token::getCurrentUid();
        $old_password = trim(getInput('old_password'));
        $password = trim(getInput('password'));
        $repassword = trim(getInput('repassword'));

        $rule = [
            'old_password' => 'require|alphaNum|length:6,20',
            'password' => 'require|alphaNum|length:6,20|confirm:repassword',
        ];

        $msg = [
            'old_password.require' => 2003,
            'old_password.alphaNum' => 2004,
            'old_password.length' => 2005,
            'password.require' => 2003,
            'password.alphaNum' => 2004,
            'password.length' => 2005,
            'password.confirm' => 2006,
        ];
        $data = [
            'old_password' => $old_password,
            'password' => $password,
            'repassword' => $repassword
        ];
        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);
        if (!$result) {
            return show($validate->getError());
        }
        $user = UserModel::field('id,password')->where('id', '=', $uid)->find();
        if (!$user) {
            return show(2008);
        }
        if ($user['password'] != $old_password) {
            return show(2009);
        }
        if ($user['password'] == $password) {
            return show(2011);
        }
        $data = [
            'password' => $password,
            'token' => Token::generateToken(),
            'token_val' => time() + config('lock.app_token_val')
        ];
        $is_up = UserModel::where('id', '=', $uid)->update($data);
        if (!$is_up) {
            throw new BaseException(999);
        }
        $userInfo = UserModel::getUserInfo($uid);
        return show(1, $userInfo);
    }

    /**
     * Notes:资料
     *
     * DateTime: 2023/9/11 16:00
     */
    public function center()
    {
        $uid = Token::getCurrentUid();
        $info = UserModel::field('id,username,nickname,avatar')->where('id', '=', $uid)->find()->append(['nickname_status', 'avatar_status']);
        return show(1, $info);
    }

    /**
     * Notes:修改用户名
     *
     * DateTime: 2023/9/11 15:59
     */
    public function changeUsername()
    {

        $uid = Token::getCurrentUid();
        $username = trim(getInput('username'));
        $rule = [
            'username' => 'require|alphaNum|length:6,20',
        ];
        $msg = [
            'username.require' => 2001,
            'username.alphaNum' => 2002,
            'username.length' => 2020,
        ];

        $data = [
            'username' => $username,
        ];
        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);

        if (!$result) {
            return show($validate->getError());
        }
        $c = UserModel::where('username', '=', $username)->count();
        if ($c) {
            throw new BaseException(2007);
        }
        $res = UserModel::where('id', '=', $uid)->setField('username', $username);
        if (!$res) {
            throw new BaseException(999);
        }
        return show(1);
    }


    /**
     * Notes: 修改昵称和邮箱
     * DateTime: 2023/9/11 15:59
     */
    public function changeInfo()
    {
        $uid = Token::getCurrentUid();
        $nickname = trim(getInput('nickname'));
        $email = trim(getInput('email'));

        $rule = [
            'nickname' => 'alphaNum|length:6,20',
            'email' => 'require|email',
        ];

        $msg = [
            'nickname.alphaNum' => 2002,
            'nickname.length' => 2020,
            'email.require' => 2025,
            'email.email' => 2026,
        ];

        $data = [
            'nickname' => $nickname,
            'email' => $email,
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);

        if (!$result) {
            return show($validate->getError());
        }

        // 检查邮箱是否被其他用户占用
        $emailExist = UserModel::where('email', '=', $email)->where('id', '<>', $uid)->count();
        if ($emailExist) {
            throw new BaseException(2029); // 邮箱已被占用
        }

        // 构建更新数据
        $updateData = ['email' => $email];
        if ($nickname !== '') {
            $updateData['nickname'] = $nickname;
        }

        $res = UserModel::where('id', '=', $uid)->update($updateData);

        return show(1);
    }

    /**
     * Notes:获取验证码
     *
     * DateTime: 2023/10/10 17:53
     */
    public function getEmailCode()
    {
        $email = trim(getInput('email'));

        if (empty($email)) {
            return show(1000);
        }

        $is_set = UserModel::where('email', '=', $email)->value('id');
        if (!$is_set) {
            return show(2008);
        }

        $lastSendTime = session('emailSendTime_' . $email);
        if ($lastSendTime && time() - $lastSendTime < 60) {
            return show(1010);
        }

        $code = mt_rand(100000, 999999);
        $res = sendMail($email, $code);

        if ($res) {
            $data = [
                'code' => $code,
                'expire_at' => time() + 300,
                'send_at' => time()
            ];

            $exists = Db::table('verify_codes')->where('email', $email)->find();

            if ($exists) {
                Db::table('verify_codes')->where('email', $email)->update($data);
            } else {
                $data['email'] = $email;
                Db::table('verify_codes')->insert($data);
            }

            // session('emailCode_' . $email, $code);
            // session('emailTime_' . $email, time() + 300);
            // session('emailSendTime_' . $email, time());
            return show(1, '验证码发送成功');
        } else {
            return show(1009, '验证码发送失败');
        }
    }

    /**
     * Notes: 忘记密码
     * DateTime: 2023/10/10 17:54
     */
    public function forgetPassword()
    {
        $email = trim(getInput('email'));
        $password = trim(getInput('password'));
        $code = trim(getInput('code'));

        $rule = [
            'email' => 'require',
            'password' => 'require|alphaNum|length:6,20',
            'code' => 'require',
        ];

        $msg = [
            'email.require' => 2025,
            'email.email' => 2026,
            'password.require' => 2003,
            'password.alphaNum' => 2004,
            'password.length' => 2005,
            'code.require' => 1006
        ];

        $data = [
            'email' => $email,
            'password' => $password,
            'code' => $code
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);
        if (!$result) {
            return show($validate->getError());
        }

        $user = UserModel::field('id,code')->where('email', '=', $email)->find();
        if (!$user) {
            return show(2008);
        }

        // 取验证码记录
        $record = Db::table('verify_codes')->where('email', $email)->find();
        if (!$record || time() > $record['expire_at'] || $record['code'] != $code) {
            return show(1008); // 验证码错误/过期
        }

        // $sessionCode = session('emailCode_' . $email);
        // $sessionTime = session('emailTime_' . $email);
        // if (!$sessionCode || time() > $sessionTime || $code != $sessionCode) {
        //     return show(1008);
        // }

        $updateData = [
            'password' => $password,
            'token' => Token::generateToken(),
            'token_val' => time() + config('lock.app_token_val')
        ];

        $is_up = UserModel::where('id', '=', $user['id'])->update($updateData);
        if (!$is_up) {
            throw new BaseException(999);
        }

        // 验证通过后可删除验证码记录
        Db::table('verify_codes')->where('email', $email)->delete();

        // session('emailCode_' . $email, null);
        // session('emailTime_' . $email, null);

        $userInfo = UserModel::getUserInfo($user['id']);
        return show(1, $userInfo);
    }


    /**
     * Notes:我的订阅
     *
     * DateTime: 2023/11/15 19:00
     */
    public function subscribe()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::subscribe($uid, $page, $limit, $lang);
        return show(1, $lists);
    }

    /**
     * Notes:我的收藏
     *
     * DateTime: 2023/11/14 19:00
     */
    public function favorite()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::favorites($uid, $page, $limit, $lang);
        return show(1, $lists);
    }

    /**
     * Notes:观看历史
     *
     * DateTime: 2023/11/15 18:59
     */
    public function history()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';
        $lists = Manhua::history($uid, $page, $limit, $lang);
        return show(1, $lists);
    }

    /**
     * Notes:消费记录
     *
     * DateTime: 2023/11/15 19:00
     */
    public function record()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lists = Record::lists($uid, $page, $limit);
        return show(1, $lists);
    }

    /**
     * Notes:设置是否自动购买
     *
     * DateTime: 2023/11/24 14:40
     */
    public function autoBuy()
    {
        $uid = Token::getCurrentUid();
        $auto_buy = getInput('auto_buy');
        if (!in_array($auto_buy, ['0', '1'])) {
            throw new BaseException(1000);
        }
        $is_up = UserModel::where('id', '=', $uid)->update(['auto_buy' => $auto_buy]);
        if ($is_up === false) {
            throw new BaseException(999);
        }
        return show(1, $auto_buy);
    }

    /**
     * Notes:添加观看记录
     *
     * DateTime: 2023/12/17 13:20
     */
    public function addHistory()
    {
        $chaInfo = Chapter::getChaInfo();
        $uid = getUid();
        if ($uid) {
            History::add_log($uid, $chaInfo['manhua_id'], $chaInfo['id']);
        }
        return show(1);
    }

    /**
     * Notes:清除观看记录
     *
     * DateTime: 2023/12/17 13:20
     */
    public function clearHistory()
    {
        $uid = Token::getCurrentUid();
        $is_up = History::where('member_id', '=', $uid)->delete();
        if ($is_up === false) {
            throw new BaseException(999);
        }
        return show(1);
    }


    /**
     * Notes:兑换码
     *
     * DateTime: 2023/12/17 13:20
     */
    public function redeemCode()
    {
        $uid = Token::getCurrentUid();
        $code = trim(getInput('code'));
        if (empty($code)) {
            return show(2021);
        }

        $check = CodeModel::field('id,status,uid,batch,value')->where('code', '=', $code)->find();

        if (!$check) {
            return show(2022);
        }
        if ($check['status'] == 1) {
            return show(2023);
        }

        $row = CodeModel::where('uid', '=', $uid)->where('batch', '=', $check['batch'])->count();
        if ($row) {
            return show(2024);
        }

        $time = time();
        $update = [
            'status' => 1,
            'exchange_time' => $time,
            'uid' => $uid
        ];

        Db::startTrans();
        try {
            CodeModel::where('id', '=', $check['id'])->update($update);
            $userInfo = UserModel::field('id,viptime')->where('id', '=', $uid)->find();
            if ($userInfo['viptime'] > $time) {
                $user['viptime'] = $userInfo['viptime'] + $check['value'] * 86400;
            } else {
                $user['viptime'] = $time + $check['value'] * 86400;
            }
            UserModel::where('id', '=', $uid)->update($user);
            Db::commit();
            return show(1);
        } catch (\Exception $e) {
            Db::rollback();
            return show(0);
        }
    }

    /**
     * Notes:用户订单表
     *
     * DateTime: 2025/4/23 13:20
     */
    public function orderList()
    {
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page')) ? (int) getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int) getInput('limit') : 12;
        $lang = !empty(getInput('lang')) ? getInput('lang') : 'zh';

        $lists = Order::lists($uid, $page, $limit, $lang);
        return show(1, $lists);
    }
}
