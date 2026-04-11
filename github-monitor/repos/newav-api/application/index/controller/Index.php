<?php
namespace app\index\controller;

use app\index\model\FrontendImages;
use app\index\model\Actors;
use app\index\model\Publisher;
use app\index\model\Video;
use app\index\model\Group;
use app\index\model\ActorSubscribe;
use app\index\model\Token;
use app\index\model\Vip;
use app\index\model\Coin;
use app\index\model\Point;

class Index extends Base
{

    const list_limit = 20;

    public function index(){
        return show(1);
    }

    public function globalSearch() {
        $page    = (int)getInput('page', 1);
        $limit   = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $keyword = trim(getInput('keyword'));
        
        $actorData     = Actors::lists($page, $limit, 2, $keyword);
        $publisherData = Publisher::lists($page, $limit, $keyword);
        $videoData     = Video::lists($page, $limit, null, null, null, $keyword, '', '', '');
        $groupData     = Group::lists($page, $limit, $keyword, false);

        // Compose result
        $result = [
            'actor'        => $actorData,
            'publisher'    => $publisherData,
            'video'        => $videoData,
            'video_groups' => $groupData,
            'total'        => $actorData['total'] + $publisherData['total'] + $videoData['total'] + $groupData['total']
        ];

        return show(1, $result);
    }

    public function globalMySubscribe() {
        $uid   = Token::getCurrentUid();
        $page  = (int)getInput('page', 1);
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;

        // Actor list
        $actorData     = ActorSubscribe::lists($uid, $page, $limit);

        // Publisher list
        $publisherData = Publisher::mySubscribe($uid, $page, $limit);

        // Compose result
        $result = [
            'actor'        => $actorData,
            'publisher'    => $publisherData
        ];

        return show(1, $result);
    }

    public function globalVip() {
        $vipLists   = Vip::lists();
        $pointLists = Point::lists();
        $coinLists  = Coin::lists();

        // Compose result
        $result = [
            'vip'   => $vipLists,
            'coin'  => $coinLists,
            'point' => $pointLists
        ];
        return show(1, $result);
    }

    public function globalImage() {
        $imgLists   = FrontendImages::lists();
        return show(1, $imgLists);
    }
}
