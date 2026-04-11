<?php

namespace app\index\controller;

use app\index\model\Actor2;
use app\index\model\Actor4k;
use app\index\model\Actors;
use app\index\model\ActorSubscribe;
use app\index\model\ActorSubscribe1;
use app\index\model\ActorSubscribe2;
use app\index\model\ActorSubscribe4k;
use app\index\model\Token;
use app\index\model\TrendDetail;

class Actor extends Base
{

    /**
     * Notes:演员列表
     *
     * DateTime: 2023/8/13 21:39
     */
    public function lists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):30;
        $order = !empty(getInput('order'))?getInput('order'):2;
        $keyword = getInput('keyword');

        switch ($this->site){
            case 1:
            case 2:
                $lists = Actors::lists($page,$limit,$order,$keyword,$this->site);
                break;
            case 3:
                $lists = Actor2::lists($page,$limit,$keyword);
                break;
            case 4:
                $lists = Actor4k::lists($page,$limit,$order,$keyword);
                break;
        }

        return show(1,$lists);
    }


    /**
     * Notes:演员详情
     *
     * DateTime: 2023/8/13 22:38
     */
    public function info(){
        switch ($this->site){
            case 1:
            case 2:
                $model = new Actors();
                break;
            case 3:
                $model = new Actor2();
                break;
            case 4:
                $model = new Actor4k();
                break;
        }
        $aid = $model::getAid();
        $info = $model::info($aid);
        return show(1,$info);
    }

    /**
     * Notes:演员动态
     *
     * DateTime: 2023/8/13 22:38
     */
    public function trend(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):10;
        switch ($this->site){
            case 4:
                $model = new Actor4k();
                break;
            case 2:
            case 1:
            default:
                $model = new Actors();
                break;
        }
        $aid = $model::getAid();
        $info= $model::info($aid);
        $lists = TrendDetail::lists($page,$limit,$info['tid']);
        return show(1,$lists);
    }


    /**
     * Notes:演员订阅
     *
     * DateTime: 2024/7/2 下午3:35
     */
    public function subscribe(){
        $uid = Token::getCurrentUid();
        switch ($this->site){
            case 1:
                $model = new Actors();
                $subModel = new ActorSubscribe();
                break;
            case 2:
                $model = new Actors();
                $subModel = new ActorSubscribe1();
                break;
            case 3:
                $model = new Actor2();
                $subModel = new ActorSubscribe2();
                break;
            case 4:
                $model = new Actor4k();
                $subModel = new ActorSubscribe4k();
                break;
        }
        $actor_id = $model::getAid();
        $subscribe_id = $subModel::where('uid','=',$uid)->where('actor_id','=',$actor_id)->value('id');
        if($subscribe_id){
            $row = $subModel::destroy($subscribe_id);
            $status = 0;
        }else{
            $add_data = [
                'uid'=>$uid,
                'actor_id'=>$actor_id,
                'add_time'=>time()
            ];
            $row = $subModel::insert($add_data);
            $status = 1;
        }
        if(!$row){
            return show(0);
        }
        return show(1,$status);
    }


    /**
     * Notes:我的订阅
     *
     * DateTime: 2024/7/2 下午3:30
     */
    public function mySubscribe(){
        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):20;
        switch ($this->site){
            case 1:
                $model = new ActorSubscribe();
                break;
            case 2:
                $model = new ActorSubscribe1();
                break;
            case 3:
                $model = new ActorSubscribe2();
                break;
            case 4:
                $model = new ActorSubscribe4k();
                break;
        }

        $lists = $model::lists($uid,$page,$limit);
        return show(1,$lists);

    }

}