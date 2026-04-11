<?php

namespace app\index\controller;
use app\index\model\Code;
use app\index\model\Configs;
use app\index\model\Feedback;
use app\index\model\Feedback1;
use app\index\model\Feedback2;
use app\index\model\Feedback3;
use app\index\model\InviteLog;
use app\index\model\Token;
use app\index\model\Tongji;
use app\index\model\User as UserModel;
use app\index\model\Video as VideoModel;
use app\index\model\VideoPlayLog;
use app\lib\exception\BaseException;
use think\Db;
use think\facade\Validate;
use think\facade\Request;
class User extends Base
{
    /**
     * Notes:注册
     *
     * DateTime: 2022/4/21 15:39
     */
    public function register(){

        $username   = trim(getInput('username'));
        $password   = trim(getInput('password'));
        $repassword = trim(getInput('repassword'));
        $agent_code = trim(getInput('agent_code'));

        $rule = [
            'username' => 'require|alphaNum|length:6,20',
            'password' => 'require|alphaNum|length:6,20|confirm:repassword',
        ];

        Request::except('password_confirm','post');

        $msg = [
            'username.require'  => 2001,
            'username.alphaNum' => 2002,
            'username.length'   => 2019,
            'password.require'  => 2003,
            'password.alphaNum' => 2004,
            'password.length'   => 2005,
            'password.confirm'  => 2006
        ];
        $data = [
            'username'   => $username,
            'password'   => $password,
            'repassword' => $repassword,
            'agent_code' => $agent_code
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);

        if(!$result){
            return show($validate->getError());
        }
        $is_name = UserModel::where('username','=',$username)->value('id');
        if($is_name){
            throw new BaseException(2007);
        }

        $pid = 0;
        $invitation_code = getInput('invitation_code');
        if(!empty($invitation_code)){
            $pid = UserModel::where('code','=',$invitation_code)->value('id');
            if(!$pid){
                return show(2021);
            }
        }

        $x = 1;
        do {
            $code = strtolower(getRandChar(6));
            $user = UserModel::where('code','=',$code)->value('id');
            $x++;
        } while ($user != null && $x < 5); //检查5次避免重复，通常只跑一次
        if($x == 5) throw new BaseException(999);

        $p = !empty(getInput('p'))?getInput('p'):'';

        $time = time();
        $site = $this->site;
        $data = [
            'username'     => $username,
            'ori_password' => $password,
            'password'     => md5($code.$password),
            'code'         => $code,
            'agent_code'   => $agent_code,
            'pid'          => $pid,
            'reg_time'     => $time,
            'p'            => $p,
            'login_time'   => $time,
            'token'        => Token::generateToken(),
            'token_val'    => $time+config('lock.app_token_val'),
        ];
        Db::startTrans();
        try {
            $last_id =UserModel::insertGetId($data);
            if($pid){
                $three_time = strtotime("-3 day");
                $is_invite = InviteLog::where('pid','=',$pid)->where('type','=',$site)->whereTime('add_time','>',$three_time)->value('id');
                $inviteLog = [
                    'uid'=>$last_id,
                    'pid'=>$pid,
                    'type'=>$site,
                    'add_time'=>$time
                ];
                InviteLog::insert($inviteLog);
                $inviteInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time')->where('id','=',$pid)->find();
                if(!$inviteInfo){
                    Db::rollback();
                    return show(2021);
                }
                if($is_invite){
                    Db::commit();
                    $userInfo = UserModel::getUserInfo($last_id);
                    return show(1,$userInfo);
                }
                switch ($site){
                    case 1:
                        if ($inviteInfo['vip_end_time'] > $time) {
                            $user['vip_end_time'] = $inviteInfo['vip_end_time']+3600;
                        }else{
                            $user['vip_begin_time'] = $time;
                            $user['vip_end_time'] = $time+3600;
                        }
                        break;
                    case 2:
                        if ($inviteInfo['wm_end_time'] > $time) {
                            $user['wm_end_time'] = $inviteInfo['wm_end_time']+3600;
                        }else{
                            $user['wm_begin_time'] = $time;
                            $user['wm_end_time'] = $time+3600;
                        }
                        break;
                    case 3:
                        if ($inviteInfo['dm_end_time'] > $time) {
                            $user['dm_end_time'] = $inviteInfo['dm_end_time']+3600;
                        }else{
                            $user['dm_begin_time'] = $time;
                            $user['dm_end_time'] = $time+3600;
                        }
                        break;
                    case 4:
                        if ($inviteInfo['k4_end_time'] > $time) {
                            $user['k4_end_time'] = $inviteInfo['k4_end_time']+3600;
                        }else{
                            $user['k4_begin_time'] = $time;
                            $user['k4_end_time'] = $time+3600;
                        }
                        break;
                }
                UserModel::where('id', '=', $inviteInfo['id'])->update($user);
            }
            if($p)
            {
                $cpa = [
                    'u_id'=>$last_id,
                    'agency_code'=>$p
                ];
                $cpa_url = Configs::get('cpa_url_reg');
                $res = httpPost($cpa_url,$cpa);
                save_log($res,'cpa-'.$p);
            }
            $this->handleReg();
        }catch (\Exception $e){
            Db::rollback();
            throw new BaseException(999);
        }

        Db::commit();
        $userInfo = UserModel::getUserInfo($last_id);
        // UserModel::addLog($last_id);
        return show(1,$userInfo);
    }

    private function handleReg(){
        $date = date('Y-m-d');
        $is_set = Tongji::where('date','=',$date)->value('id');
        if($is_set){
            Tongji::where('id','=',$is_set)->setInc('reg_num');
        }else{
            $add = [
                'date'=>$date
            ];
            Tongji::insert($add);
        }

    }

    /**
     * Notes:用户名登陆
     *
     * DateTime: 2022/4/21 16:24
     */
    public function login(){
        $username = trim(getInput('username'));
        $password = trim(getInput('password'));
        $rule = [
            'username' => 'require|alphaNum|length:6,20',
            'password'=>'require|alphaNum|length:6,20',
        ];
        $msg = [
            'username.require' => 2001,
            'username.alphaNum'     => 2002,
            'username.length'   => 2019,
            'password.require' => 2003,
            'password.alphaNum'     => 2004,
            'password.length'   => 2005,
        ];
        $data = [
            'username'  => $username,
            'password'   => $password,
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);
        if(!$result){
            return show($validate->getError());
        }
        $user = UserModel::field('id,status,ori_password')->where('username','=',$username)->find();
        if(!$user){
            throw new BaseException(2008);
        }
        if($user['ori_password'] != $password){
            throw new BaseException(2009);
        }
        if($user['status'] == 1){
            throw new BaseException(2010);
        }
        $time = time();

        $data = [
            'token' => Token::generateToken(),
            'token_val'=>$time+config('lock.app_token_val'),
            'login_time'=>$time
        ];
        $is_up = UserModel::where('id', '=', $user['id'])->update($data);
        // UserModel::addLog($user['id']);
        if (!$is_up) {
            throw new BaseException(999);
        }
        $userInfo = UserModel::getUserInfo($user['id']);
        return show(1,$userInfo);
    }

    /**
     * Notes:退出
     *
     * DateTime: 2022/5/19 16:00
     */
    public function logout(){
        $uid = Token::getCurrentUid();
        $data = [
            'token' => Token::generateToken(),
            'token_val'=>time()-1
        ];
        $is_up = UserModel::where('id','=',$uid)->update($data);
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
    public function info(){

        $uid = Token::getCurrentUid();
        $info = UserModel::getUserInfo($uid);
        return show(1,$info);
    }


    /**
     * Notes:反馈
     *
     * DateTime: 2023/8/1 1:31
     */
    public function feedback(){
        $uid = Token::getCurrentUid();
        switch ($this->site){
            case 1:
                $model = new Feedback();
                break;
            case 2:
                $model = new Feedback1();
                break;
            case 3:
                $model = new Feedback2();
                break;
            case 4:
                $model = new Feedback3();
                break;
        }

        if($uid){
            $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
            $count = $model::where('uid','=',$uid)->where('add_time','>=',$beginToday)->count();
            if($count>=5){
                return show(2013);
            }
        }
        $title = trim(getInput('title'));
        $content = trim(getInput('content'));
        $rule = [
            'title'  => 'require|max:100',
            'content'   => 'require|max:255',
        ];
        $msg = [
            'title.require' => 2014,
            'content.require'     =>2015,
            'content.max' => 2017,
        ];

        $data = [
            'title'  => $title,
            'content'   => $content,
        ];

        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);
        if(!$result){
            return show($validate->getError());
        }

        $data = [
            'uid'=>$uid,
            'title'=>$title,
            'content'=>$content,
            'add_time'=>time()
        ];

        $res = $model::insert($data);
        if(!$res){
            return show(0);
        }
        return show(1);
    }

    /**
     * Notes:修改密码
     *
     * DateTime: 2023/9/3 22:19
     */
    public function changepassword(){
        $uid = Token::getCurrentUid();
        $old_password = trim(getInput('old_password'));
        $password = trim(getInput('password'));
        $repassword = trim(getInput('repassword'));

        $rule = [
            'old_password'   => 'require|alphaNum|length:6,20',
            'password'=>'require|alphaNum|length:6,20|confirm:repassword',
        ];

        $msg = [
            'old_password.require' => 2003,
            'old_password.alphaNum'     => 2004,
            'old_password.length'   => 2005,
            'password.require' => 2003,
            'password.alphaNum'     => 2004,
            'password.length'   => 2005,
            'password.confirm'   => 2006,
        ];
        $data = [
            'old_password'  => $old_password,
            'password'   => $password,
            'repassword'=>$repassword
        ];
        $validate = Validate::make($rule)->message($msg);
        $result = $validate->check($data);
        if(!$result){

            return show($validate->getError());
        }

        $user = UserModel::field('id,code,ori_password')->where('id','=',$uid)->find();

        if (!$user) {
            throw new BaseException(2008);
        }

        if ($user['ori_password'] != $old_password) {
            throw new BaseException(2009);
        }

        if($user['ori_password'] == $password){
            throw new BaseException(2011);
        }

        $data = [
            'ori_password'=>$password,
            'password' => md5($user['code'].$password),
            'token' => Token::generateToken(),
            'token_val'=>time()+config('lock.app_token_val')
        ];
        $is_up = UserModel::where('id', '=', $uid)->update($data);
        if (!$is_up) {
            throw new BaseException(999);
        }
        $userInfo = UserModel::getUserInfo($uid);
        return show(1,$userInfo);
    }

    /**
     * Notes:我的收藏
     *
     * DateTime: 2023/9/9 19:52
     */
    public function myLike(){
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = VideoModel::userLike($uid,$page,$limit);
        return show(1,$lists);
    }


    /**
     * Notes:观看记录
     *
     * DateTime: 2023/9/9 19:55
     */
    public function playLog(){
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = VideoModel::userPlayLog($uid,$page,$limit);;
        return show(1,$lists);
    }

    /**
     * Notes:清空观看记录
     *
     * DateTime: 2023/9/9 19:55
     */
    public function clearPlayLog(){
        $uid = Token::getCurrentUid();
        $res = VideoPlayLog::where('uid','=',$uid)->delete();
        if($res === false){
            throw new BaseException(999);
        }
        return show(1);
    }


    /**
     * Notes:资料
     *
     * DateTime: 2023/9/11 16:00
     */
    public  function center(){
        $uid = Token::getCurrentUid();
        $info = UserModel::field('id,username,nickname,avatar')->where('id','=',$uid)->find();
        return show(1,$info);
    }


    /**
     * Notes:修改资料
     *
     * DateTime: 2024/6/27 下午9:56
     */
    public function editInfo(){

        $uid = Token::getCurrentUid();
        $signature = getInput('signature');
        if(empty($signature)) {
            throw new BaseException(1000);
        }
        $is_up = UserModel::where('id','=',$uid)->setField('signature',$signature);
        if($is_up === false){
            throw new BaseException(999);
        }
        return show(1);
    }

    /**
     * Notes:兑换码
     *
     * DateTime: 2024/7/19 下午2:34
     */
    public function redeemcode(){
        $uid = Token::getCurrentUid();
        $code = trim(getInput('code'));
        if (empty($code)) {
            throw new BaseException(2022);
        }
        $check = Code::field('id,status,uid,batch,day')->where('type','=',$this->site)->where('code','=',$code)->find();
        if(!$check){
            throw new BaseException(2023);
        }
        if($check['status'] == 1){
            throw new BaseException(2024);
        }
        $is_use = Code::where('uid','=',$uid)->where('batch','=',$check['batch'])->value('id');
        if($is_use){
            throw new BaseException(2025);
        }
        $time = time();
        $data = [
            'uid'=>$uid,
            'status'=>1,
            'exchange_time'=>$time
        ];
        Db::startTrans();
        try {
            Code::where('id', '=', $check['id'])->update($data);
            $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time')->where('id','=',$uid)->find();
            switch ($this->site){
                case 1:
                    if ($userInfo['vip_end_time'] > $time) {
                        $user['vip_end_time'] = $userInfo['vip_end_time']+3600;
                    }else{
                        $user['vip_begin_time'] = $time;
                        $user['vip_end_time'] = $time+3600;
                    }
                    break;
                case 2:
                    if ($userInfo['wm_end_time'] > $time) {
                        $user['wm_end_time'] = $userInfo['wm_end_time']+3600;
                    }else{
                        $user['wm_begin_time'] = $time;
                        $user['wm_end_time'] = $time+3600;
                    }
                    break;
                case 3:
                    if ($userInfo['dm_end_time'] > $time) {
                        $user['dm_end_time'] = $userInfo['dm_end_time']+3600;
                    }else{
                        $user['dm_begin_time'] = $time;
                        $user['dm_end_time'] = $time+3600;
                    }
                    break;
                case 4:
                    if ($userInfo['k4_end_time'] > $time) {
                        $user['k4_end_time'] = $userInfo['k4_end_time']+3600;
                    }else{
                        $user['k4_begin_time'] = $time;
                        $user['k4_end_time'] = $time+3600;
                    }
                    break;
            }

            UserModel::where('id', '=', $uid)->update($user);
            Db::commit();
            return show(1);

        }catch (\Exception $e){
            Db::rollback();
            throw new BaseException(999);

        }


    }


    public function getVipStatus()
    {

        $uid = Token::getCurrentUid();
        $site = $this->site;
        $userInfo = UserModel::field('id,vip_end_time,wm_end_time,dm_end_time,k4_end_time')->where('id','=',$uid)->find();
        $data = [
            'vip_status'=>0,
            'vip_time'=>0
        ];
        switch ($site){
            case 1:
                if ($userInfo['vip_end_time'] > time()) {
                    $data['vip_status'] = 1;
                    $data['vip_time'] = $userInfo['vip_end_time'];
                }
                break;
            case 2:
                if ($userInfo['wm_end_time'] > time()) {
                    $data['vip_status'] = 1;
                    $data['vip_time'] = $userInfo['wm_end_time'];
                }
                break;
            case 3:
                if ($userInfo['dm_end_time'] > time()) {
                    $data['vip_status'] = 1;
                    $data['vip_time'] = $userInfo['dm_end_time'];
                }
                break;
            case 4:
                if ($userInfo['k4_end_time'] > time()) {
                    $data['vip_status'] = 1;
                    $data['vip_time'] = $userInfo['k4_end_time'];
                }
                break;
        }
        return show(1,$data);



    }

}