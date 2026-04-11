<?php

namespace app\index\controller;
use app\index\model\Publisher as PublisherModel;
use app\index\model\Token;
use app\lib\exception\BaseException;

class Publisher extends Base
{
    /**
     * Notes:片商列表
     *
     * DateTime: 2024/7/2 下午3:40
     */
    public function lists(){
        $page    = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $keyword = getInput('keyword');
        $lists   = PublisherModel::lists($page,$limit,$keyword);
        return show(1,$lists);
    }

    public function info(){
        $pid  = getInput('pid');
        $info = PublisherModel::info($pid)->toArray();
        return show(1,$info);
    }

    public function subscribe()
    {
        $uid = Token::getCurrentUid();
        $pid = getInput('pid');

        if(!$pid){
            throw new BaseException(3005);
        }

        $res = PublisherModel::subscribe($uid, $pid);

        if ($res === true) {
            return show(1, ['isSubscribed' => true, 'msg' => 'Publisher subscribed']);
        } elseif ($res === false) {
            return show(1, ['isSubscribed' => false, 'msg' => 'Publisher unsubscribed']);
        } else {
            return show(0, ['isSubscribed' => false, 'msg' => 'Something Wrong']);
        }
    }
    
    public function mySubscribe(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = PublisherModel::mySubscribe($uid,$page,$limit);
        return show(1,$lists);
    }
}