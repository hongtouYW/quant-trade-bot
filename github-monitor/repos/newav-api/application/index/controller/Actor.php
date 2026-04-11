<?php

namespace app\index\controller;

use app\index\model\Actors;
use app\index\model\ActorSubscribe;
use app\index\model\Token;
use app\index\model\TrendDetail;
use app\lib\exception\BaseException;

class Actor extends Base
{

    /**
     * Notes:演员列表
     *
     * DateTime: 2023/8/13 21:39
     */
    public function lists(){
        $page    = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $order   = !empty(getInput('order'))?getInput('order'):2;
        $keyword = getInput('keyword');

        $lists = Actors::lists($page,$limit,$order,$keyword);
        return show(1,$lists);
    }


    /**
     * Notes:演员详情
     *
     * DateTime: 2023/8/13 22:38
     */
    public function info(){
        $info = Actors::info(getInput('aid'));
        if(!$info){
            throw new BaseException(3003);
        }
        return show(1,$info);
    }

    /**
     * Notes:演员动态
     *
     * DateTime: 2023/8/13 22:38
     */
    public function trend(){
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):10;
        $aid   = Actors::getAid();
        $info  = Actors::info($aid);
        $lists = TrendDetail::lists($page,$limit,$info['tid']);
        return show(1,$lists);
    }


    /**
     * Notes:演员订阅
     *
     * DateTime: 2024/7/2 下午3:35
     */
    public function subscribe()
    {
        $uid      = Token::getCurrentUid();
        $actor_id = getInput('aid');

        if(!$actor_id){
            throw new BaseException(3003);
        }

        $res = ActorSubscribe::subscribe($uid, $actor_id);

        if ($res === true) {
            return show(1, ['isSubscribed' => true, 'msg' => 'Actor subscribed']);
        } elseif ($res === false) {
            return show(1, ['isSubscribed' => false, 'msg' => 'Actor unsubscribed']);
        } else {
            return show(0, ['isSubscribed' => false, 'msg' => 'Something Wrong']);
        }
    }

    /**
     * Notes:我的订阅
     *
     * DateTime: 2024/7/2 下午3:30
     */
    public function mySubscribe(){  
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = ActorSubscribe::lists($uid,$page,$limit);
        return show(1,$lists);
    }

    /**
     * Get actors by publisher
     */
    public function byPublisher()
    {
        $pid   = getInput('pid');
        $page  = !empty(getInput('page')) ? (int)getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int)getInput('limit') : 20;

        if (!$pid) {
            throw new BaseException(3005); // Invalid publisher ID
        }

        $actors = Actors::byPublisher($pid, $page, $limit);
        return show(1, $actors);
    }

    public function actorRanking()
    {
        $filter = getInput('filter', 'day');
        $page   = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):30;

        if (!in_array($filter, ['day', 'week', 'month'])) {
            throw new BaseException(4000, 'Invalid filter');
        }

        $ranking = Actors::getActorRanking($filter, $page, $limit);
        return show(1, $ranking);
    }
}