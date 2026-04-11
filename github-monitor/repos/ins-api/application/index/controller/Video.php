<?php

namespace app\index\controller;
use app\index\model\Video2;
use app\index\model\Video3;
use app\index\model\Video4;
use app\index\model\VideoCollect;
use app\index\model\VideoCollect1;
use app\index\model\VideoCollect2;
use app\index\model\VideoCollect4k;
use app\index\model\Token;
use app\index\model\Video as VideoModel;
use app\index\model\VideoPlayLog;
use app\index\model\VideoPlayLog2;
use app\index\model\VideoPlayLog3;
use app\index\model\VideoPlayLog4;
use app\lib\exception\BaseException;

class Video extends Base
{

    const list_limit = 20;


    /**
     * Notes:获取首页视频列表
     *
     * DateTime: 2022/4/27 14:48
     */
    public function indexLists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $type = !empty(getInput('type'))?(int)getInput('type'):1;
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }


        $lists = $model::indexLists($page,$limit,$type);
        return show(1,$lists);

    }



    /**
     * Notes:获取视频列表
     *
     * DateTime: 2023/8/8 16:28
     */
    public function lists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $tag_id = getInput('tag_id');
        $actor_id = getInput('actor_id');
        $publisher_id = getInput('publisher_id');
        $keyword = getInput('keyword');
        $type = !empty(getInput('type'))?(int)getInput('type'):'';
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $list = $model::lists($page,$limit,$tag_id,$actor_id,$publisher_id,$keyword,$type);
        return show(1,$list);
    }

    /**
     * Notes:获取热门视频
     *
     * DateTime: 2022/4/27 14:48
     */
    public function hotLists(){
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):self::list_limit;
        $order = !empty(getInput('order'))?getInput('order'):3;
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $list = $model::hotLists($page,$limit,$order);
        return show(1,$list);
    }

    /**
     * Notes:获取视频详情
     *
     * DateTime: 2022/5/1 16:30
     */
    public function info(){
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $vid = $model::getVid();
        $info = $model::info($vid)->hidden(['video_url'])->toArray();
        return show(1,$info);
    }

    /**
     * Notes:获取视频播放链接
     *
     * DateTime: 2022/5/1 16:29
     */
    public function getVideoUrl(){
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $vid = $model::getVid();
        $video_url = $model::video_url($vid);
        return show(1,$video_url);
    }

    /**
     * Notes:收藏视频
     *
     * DateTime: 2022/4/26 23:54
     */
    public function collect(){
        $uid = Token::getCurrentUid();
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                $collectModel = new VideoCollect();
                break;
            case 2:
                $model = new Video2();
                $collectModel = new VideoCollect1();
                break;
            case 3:
                $model = new Video3();
                $collectModel = new VideoCollect2();
                break;
            case 4:
                $model = new Video4();
                $collectModel = new VideoCollect4k();
                break;
        }
        $vid = $model::getVid();
        $collect_id  = $collectModel->where('uid',$uid)->where('vid',$vid)->value('id');
        if($collect_id){
            $row  = $collectModel->destroy($collect_id);
            $data = 0;
        }else{
            $add_data = [
                'uid'=>$uid,
                'vid'=>$vid,
                'add_time'=>time()
            ];
            $row = $collectModel->insert($add_data);
            $data = 1;
        }
        if(!$row){
            throw new BaseException(999);
        }
        return show(1,$data);
    }

    /**
     * Notes:我得收藏
     *
     * DateTime: 2023/12/22 19:22
     */
    public function myCollect(){

        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):12;
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $lists = $model::myCollect($uid,$page,$limit);
        return show(1,$lists);
    }





    /**
     * Notes:清除收藏
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function clearCollect(){

        $uid = Token::getCurrentUid();
        $vid = getInput('vid');
        switch ($this->site){
            case 1:
                $model = new VideoCollect();
                break;
            case 2:
                $model = new VideoCollect1();
                break;
            case 3:
                $model = new VideoCollect2();
                break;
            case 4:
                $model = new VideoCollect4k();
                break;
        }
        $row = $model->where('uid',$uid)->where('vid','in',$vid)->delete();
        if($row === false){
            throw new BaseException(999);
        }
        return show(1);
    }



    /**
     * Notes:播放记录
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function myPlayLog(){

        $uid = Token::getCurrentUid();
        $page = !empty(getInput('page'))?(int)getInput('page'):1;
        $limit  = !empty(getInput('limit'))?(int)getInput('limit'):12;
        switch ($this->site){
            case 1:
                $model = new VideoModel();
                break;
            case 2:
                $model = new Video2();
                break;
            case 3:
                $model = new Video3();
                break;
            case 4:
                $model = new Video4();
                break;
        }
        $lists = $model::myPlayLog($uid,$page,$limit);
        return show(1,$lists);
    }


    /**
     * Notes:清除播放记录
     *
     * DateTime: 2024/7/19 下午2:14
     */
    public function clearPlayLog(){

        $uid = Token::getCurrentUid();
        switch ($this->site){
            case 1:
                $model = new VideoPlayLog();
                break;
            case 2:
                $model = new VideoPlayLog2();
                break;
            case 3:
                $model = new VideoPlayLog3();
                break;
            case 4:
                $model = new VideoPlayLog4();
                break;
        }
        $row = $model->where('uid',$uid)->delete();
        if($row === false){
            throw new BaseException(999);
        }
        return show(1);
    }


}