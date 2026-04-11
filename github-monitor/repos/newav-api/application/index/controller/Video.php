<?php

namespace app\index\controller;
use app\index\model\User as UserModel;
use app\index\model\VideoCollect;
use app\index\model\Token;
use app\index\model\Video as VideoModel;
use app\index\model\VideoPlayLog;
use app\index\model\VideoGroupDetails;
use app\index\model\VideoPurchase;
use app\lib\exception\BaseException;
use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Db;

class Video extends Base
{

    const list_limit = 20;


    /**
     * Notes:获取首页视频列表
     *
     * DateTime: 2022/4/27 14:48
     */
    public function indexLists(){
        $page   = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $type   = !empty(getInput('type'))?(int)getInput('type'):'';
        $lists  = VideoModel::indexLists($page,$limit,$type);
        return show(1,$lists);

    }

    /**
     * Notes:获取视频列表
     *
     * DateTime: 2023/8/8 16:28
     */
    public function lists(){
        $page         = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit        = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $tag_id       = getInput('tag_id');
        $actor_id     = getInput('actor_id');
        $publisher_id = getInput('publisher_id');
        $publish_date = getInput('publish_date');
        $private      = getInput('private');
        $keyword      = getInput('keyword');
        $type         = !empty(getInput('type'))?(int)getInput('type'):'';
        $random       = !empty(getInput('random'))?(int)getInput('random'):0;
        $sequel       = !empty(getInput('sequel'))?(int)getInput('sequel'):0;
        $list         = VideoModel::lists($page,$limit,$tag_id,$actor_id,$publisher_id,$keyword,$type,$publish_date,$private,$random,$sequel);
        return show(1,$list);
    }

    /**
     * Notes:获取热门视频
     *
     * DateTime: 2022/4/27 14:48
     */
    public function hotLists(){
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $order = !empty(getInput('order'))?getInput('order'):3;
        $list = VideoModel::hotLists($page,$limit,$order);
        return show(1,$list);
    }

    public function relatedLists(){
        $page     = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit    = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $type     = !empty(getInput('type'))?getInput('type'):3;
        $priority = !empty(getInput('priority'))?getInput('priority'):3;
        $list = VideoModel::relatedLists($page,$limit,$type,$priority);
        return show(1,$list);
    }

    /**
     * Notes:获取视频详情
     *
     * DateTime: 2022/5/1 16:30
     */
    public function info(){
        $vid  = VideoModel::getVid();
        $info = VideoModel::info($vid)->hidden(['video_url'])->toArray();
        return show(1,$info);
    }

    /**
     * Notes:获取视频播放链接
     *
     * DateTime: 2022/5/1 16:29
     */
    public function getVideoUrl(){
        $vid       = VideoModel::getVid();
        $video_url = VideoModel::video_url($vid);
        return show(1,$video_url);
    }

    /**
     * Notes:收藏视频
     *
     * DateTime: 2022/4/26 23:54
     */
    public function collect(){
        $uid         = Token::getCurrentUid();
        $vid         = VideoModel::getVid();
        $collect_id  = VideoCollect::where('uid',$uid)->where('vid',$vid)->value('id');
        if($collect_id){
            $row  = VideoCollect::destroy($collect_id);
            return show(1, ['collect' => false, 'msg' => 'Video uncollected']);
        }else{
            $add_data = [
                'uid'      => $uid,
                'vid'      => $vid,
                'add_time' => time()
            ];
            $row = VideoCollect::insert($add_data);
            return show(1, ['collect' => true, 'msg' => 'Video collected']);
        }
        if(!$row){
            throw new BaseException(999);
        }
    }

    /**
     * Notes:我得收藏
     *
     * DateTime: 2023/12/22 19:22
     */
    public function myCollect(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):12;
        $lists = VideoModel::myCollect($uid,$page,$limit);
        return show(1,$lists);
    }

    /**
     * Notes:清除收藏
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function clearCollect(){
        $uid = Token::getCurrentUid();
        $row = VideoCollect::where('uid',$uid)->delete();
        if($row === false){
            throw new BaseException(999);
        }
        return show(1, ['clearCollect' => true, 'msg' => 'Video collect clear']);
    }



    /**
     * Notes:播放记录
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function myPlayLog(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):12;
        $lists = VideoModel::myPlayLog($uid,$page,$limit);
        return show(1,$lists);
    }


    /**
     * Notes:清除播放记录
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function clearPlayLog(){
        $uid = Token::getCurrentUid();
        $row = VideoPlayLog::where('uid',$uid)->delete();
        if($row === false){
            throw new BaseException(999);
        }
        return show(1, ['clearPlayLog' => true, 'msg' => 'Play log clear']);
    }

    public function userHasVideoAccess()
    {
        $uid       = getUid();
        // $uid       = Token::getCurrentUid();
        $vid       = VideoModel::getVid();
        $videoInfo = VideoModel::info($vid);

        if ($videoInfo['private'] == 3) { //3 means group video
            $hasAccess = VideoGroupDetails::getVideoGroup($uid, $vid);
            if (!$hasAccess) {
                return show(5007, ['access' => false, 'msg' => "User haven't purchase the video group"]);
            }
        } else {
            $hasAccess = UserModel::hasVideoAccess($uid, $videoInfo);
            if (!$hasAccess) {
                if($uid){
                    $user = UserModel::where('id', $uid)->find();
                    if($user['vip_end_time'] != null){
                        if(time() > $user['vip_end_time']){
                            return show(7001, ['access' => false, 'msg' => "VIP has expired"]);
                        }
                    }else{
                        return show(6008, ['access' => false, 'msg' => "User haven't purchase the video or VIP"]);
                    }
                }
                return show(6007, ['access' => false, 'msg' => "User haven't purchase the video"]);
            }
        }

        if ($hasAccess) {
            return show(1, ['access' => true, 'msg' => 'Success']);
        } else {
            return show(1, ['access' => false, 'msg' => 'Failed']);
        }
    }
    

    public function purchase(){
        $uid        = Token::getCurrentUid();
        $userInfo   = UserModel::getUserInfo($uid);
        $vid        = VideoModel::getVid();
        $videoInfo  = VideoModel::info($vid);
        $videoPiont = Configs::get('video_point');

        if($videoInfo['private'] == 3){
            return show(5008, ['purchase' => false, 'msg' => 'This video is under video group']);
        }elseif($videoInfo['private'] == 0){
            return show(6005, ['purchase' => false, 'msg' => 'This is free video']);
        }
        //检查冷却
        $redis_key = 'videoPurchase_'.$vid.'_'.$uid;
        $redis     = new Redis();
        $cooldown  = $redis->get($redis_key);
        if($cooldown) return show(4004);

        if (VideoPurchase::hasPurchased($uid, $vid)) {
            return show(6004, ['purchase' => false, 'msg' => 'This video had been purchased']);
        }

        if($userInfo['point'] < $videoPiont){
            return show(6001, ['purchase' => false, 'msg' => 'Insufficient point']);
        }

        Db::startTrans();

        try {// update point
            $newPoint   = $userInfo['point'] - $videoPiont;
            $userUpdate = UserModel::where('id', $uid)->update(['point' => $newPoint]);

            if (!$userUpdate) {return show(6002);}

            // insert using VideoPurchase model
            $purchase               = new VideoPurchase();
            $purchase->uid          = $uid;
            $purchase->video_id     = $vid;
            $purchase->purchased_at = date('Y-m-d H:i:s');

            if (!$purchase->save()) {return show(6003);}

            Db::commit();
            $redis->set($redis_key, 1, 30);
            return show(1, ['purchase' => true, 'msg' => 'Video purchased', 'currentPoint' => $newPoint]);

        } catch (\Exception $e) {
            Db::rollback();
            return show(0, ['purchase' => false, 'msg' => 'Something Wrong']);
        }
    }

    public function myPurchase(){
        $uid   = Token::getCurrentUid();
        $page  = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $lists = VideoModel::myPurchase($uid,$page,$limit);
        return show(1,$lists);
    }

    public function relatedVideos(){
        $page       = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit      = !empty(getInput('limit'))?(int)getInput('limit'):20;
        $related_by = !empty(getInput('related_by'))?(int)getInput('related_by'):1;
        $vid        = VideoModel::getVid();
        $lists      = VideoModel::getRelatedVideos($vid,$page,$limit,$related_by);
        return show(1,$lists);
    }

    public function userRecommendedVideos()
    {
        $page  = !empty(getInput('page')) ? (int)getInput('page') : 1;
        $limit = !empty(getInput('limit')) ? (int)getInput('limit') : 20;
        $by    = !empty(getInput('by')) ? (int)getInput('by') : 1;
        $uid   = getUid();

        // Not login user return normal list (random)
        if (!$uid || $by == 5) {
            $lists = VideoModel::lists($page,$limit,null,null,null,null,null,null,null,1,0);
            return show(1, $lists);
        }

        // login user then recommended engine
        $lists = VideoModel::getUserRecommendedVideos($uid, $page, $limit, $by);
        return show(1, $lists);
    }
}