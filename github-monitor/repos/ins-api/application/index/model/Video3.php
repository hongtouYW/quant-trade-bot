<?php

namespace app\index\model;
use think\cache\driver\Redis;
use think\Db;
use think\Model;
use app\lib\exception\BaseException;

class Video3 extends Model
{
    protected $table = 'comic';

    const list_field = 'id,title,title_en,title_ru,play,actor,thumb,preview,private';
    const info_field = 'id,title,title_en,title_ru,tags,actor,thumb,preview,panorama,video_url,description,description_en,description_ru,private,duration,play,publish_date';
    const inter_field = 'a.id,a.title,a.title_en,a.title_ru,a.play,a.actor,a.thumb,a.preview,a.private';
    const domain_path = "/hanime1";

    protected $hidden = ['title_en','title_ru','description_en','description_ru'];
    protected $append = ['collect_count'];


    public function getIsCollectAttr($val,$data){
        $is_collect = 0;
        $count = VideoCollect2::where('uid','=',getUid())->where('vid','=',$data['id'])->count();
        if($count){
            $is_collect = 1;
        }
        return $is_collect;
    }

    public function getIsSubscribeAttr($val,$data){
        $is_subscribe = 0;
        $count = ActorSubscribe2::where('uid','=',getUid())->where('actor_id','=',$data['actor'])->count();
        if($count){
            $is_subscribe = 1;
        }
        return $is_subscribe;
    }


    public function getTitleAttr($val,$data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['title_en'])){
            return $data['title_en'];
        }
        if($lang == 'ru' && !empty($data['title_ru'])){
            return $data['title_ru'];
        }
        return $val;
    }

    public function getDescriptionAttr($val,$data)
    {
        $lang = getLang();
        if ($lang == 'en' && !empty($data['description_en'])){
            return $data['description_en'];
        }
        if($lang == 'ru' && !empty($data['description_ru'])){
            return $data['description_ru'];
        }
        return $val;
    }

    public function getActorAttr($val){
        if($val) {
            $val = Actor2::field('id,name,name_en,name_ru,image')->where('id','=',$val)->find();
        }
        return $val;
    }
    public function getCollectCountAttr($val,$data){
        return VideoCollect2::where('vid','=',$data['id'])->count();
    }

    // public function getThumbAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('dm_thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }

    // public function getPreviewAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('dm_thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }

    // public function getPanoramaAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('dm_thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }
    
    public function getThumbAttr($val){
        if(!empty($val)){
            $val = self::domain_path.$val;
        }
        return $val;
    }

    public function getPreviewAttr($val){
        if(!empty($val)){
            $val = self::domain_path.$val;
        }
        return $val;
    }

    public function getPanoramaAttr($val){
        if(!empty($val)){
            $val = self::domain_path.$val;
        }
        return $val;
    }

    public function getZimuAttr($val){
        $thumb_url = Configs::get('no_au_thumb_url');
        if(!empty($val)){
            $val = $thumb_url.$val;
        }else{
            $lang = getLang();
            if ($lang == 'en')
                $val = $thumb_url.'/font/default_en.vtt';
            else if ($lang == 'ru')
                $val = $thumb_url.'/font/default_ru.vtt';
            else
                $val = $thumb_url.'/font/default.vtt';
        }
        return $val;
    }


    public function getVideoUrlAttr($val){

        if(!empty($val)){
            $video_url = Configs::get('dm_video_url');
            $val = $video_url.$val;
        }
        return $val;
    }



    private static function jianquan($url){
        $parse = parse_url($url);
        $expiryTime = 7200; // 有效期（秒）
        $secretKey = 'FoEDb2QIeVvUOyTlBJ9NMDYgJFNZ30';
        $wstime = time() + $expiryTime; // 当前时间戳 + 有效期
        $uri = $parse['path']; // 资源路径
        $ip = get_client_ip();
        $group = $secretKey . $uri . $wstime; // 生成鉴权组合：密钥 + 路径 + 时间戳
        $wsSecret = md5($group); // 使用 MD5 加密生成签名
        return $url."?wsSecret=" . $wsSecret . "&wsTime=" . $wstime."&ip=" . $ip;
    }

    public function getTagsAttr($val,$data){
        $val = [];
        if(!empty($data['tags'])) {
            $val = Tag2::field('id,name,name_en,name_ru')->where('id','in',$data['tags'])->select();
        }
        return $val;
    }



    public function getDurationAttr($val){
        return secondsToHourMinute($val);
    }


    public static function getVid(){
        $vid = getInput('vid');
        $video = self::field('id,status')->where('id','=',$vid)->find();
        if(!$video){
            throw new BaseException(3001);
        }
        if($video['status'] == 0){
            throw new BaseException(3002);
        }
        return $video['id'];
    }


    public static function info($vid)
    {
        $redis = new Redis();
        $redis_key = 'video3_info_1' . $vid;
        $results = $redis->get($redis_key);
        if (!$results) {
            $results = self::field(self::info_field)
            ->where('id','=', $vid)->find();
            if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        return $results->append(['is_collect','is_subscribe']);
    }


    public static function video_url($vid){

        $info = self::info($vid);
        $uid = getUid();
        if($info['private'] == 1){
            if(!$uid){
                throw new BaseException(2000);
            }
        }else if($info['private'] == 2){
            if(!$uid){
                throw new BaseException(2000);
            }
            $is_vip =User::is_vip2($uid);
            if(!$is_vip){
                throw new BaseException(3004);
            }
        }
        $ip = get_client_ip();
        $redis_key = 'video3_url_'.$vid.'_'.$ip;
        $redis = new Redis();
        $url = $redis->get($redis_key);
        if(!$url){
            $url = self::jianquan($info['video_url']);
            $redis->set($redis_key, $url, 7200); //5分钟
        }
        //视频增加观看次数
        self::setPlay($vid);
        if($uid){
            VideoPlayLog3::add_log($uid,$vid);
            self::tj_play($uid);
        }
        return $url;
    }

    public static function tj_play($uid){
        $redis = new Redis();
        $redis->select(6);
        $date = date('Ymd',strtotime("now"));
        $redis->zIncRby($date,'1',$uid);
    }


    public static function lists($page,$limit,$tag_id,$actor_id,$publisher_id,$keyword,$type){

        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'video_list3_1'.$tag_id.'_'.$actor_id.'_'.$keyword.'_'.$type.'_'.$page.'_'.$limit.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $where = [];
            $order = 'update_time desc';
            if($actor_id){
                $where[] = ['actor','=',$actor_id];
            }
            switch ($type){
                case 1:$where[] = ['recommend','=',1];break;
                case 2:$where[] = ['private','=',2];break;
                case 3:$where[] = ['private','=',0];break;
                default:
                    break;
            }
            $where2 = "status = 1 ";
            if($tag_id){
                $where2 .= " and instr(CONCAT( ',', tags, ',' ),  ',".$tag_id.",' )";
            }
            if($keyword){
                $where2 .= " and (instr(title, '".$keyword."') or instr(title_en, '".$keyword."') or instr(title_ru, '".$keyword."'))";
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->where($where2)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 7200);
        }
        return $results;
    }


    public static function hotLists($page,$limit,$order){
        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'video_list3_hot_1'.$order.'_'.$page.'_'.$limit.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $where = "status = 1";
            switch ($order){
                case 1:$order = 'play desc';break;
                case 2:$order = 'play_month desc';break;
                case 3:$order = 'play_week desc';break;
                case 4:$order = 'play_day desc';break;
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 7200);
        }
        return $results;
    }

    public static function indexLists($page,$limit,$type){
        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'video3_list_index_1'.$type.'_'.$page.'_'.$limit.'_'.$lang;
        $results = $redis->get($redis_key);
        $order = 'play_week desc,update_time desc';
        if(!$results){
            $where =[];
            $where[] = ['status','=',1];
            switch ($type){
                case 1:$where[] = ['recommend','=',1];break;
                case 2:$where[] = ['private','=',2];break;
                case 3:$where[] = ['private','=',0];break;
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 7200);
        }
        shuffle($results['data']);
        return $results;
    }


    public static function setPlay($vid){
        $time = time();
        $play_time=self::where('id','=',$vid)->value('play_time');
        if(date('d',$play_time)==date('d',$time)){
            $data['play_day']=Db::raw('play_day+1');
        }else{
            $data['play_day']=1;
        }
        if(date('W',$play_time)==date('W',$time)){
            $data['play_week']=Db::raw('play_week+1');
        }else{
            $data['play_week']=1;
        }
        if(date('m',$play_time)==date('m',$time)){
            $data['play_month']=Db::raw('play_month+1');
        }else{
            $data['play_month']=1;
        }
        $data['play']=Db::raw('play+1');
        $data['play_time']=time();
        self::where('id','=',$vid)->update($data);
    }

    public static function myCollect($uid,$page,$limit){
        $results = self::alias('a')
            ->join('video_collect2 b','b.vid = a.id','inner')
            ->field(self::inter_field)
            ->where('b.uid','=',$uid)
            ->where('a.status','=',1)
            ->order('b.add_time desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
        return $results;
    }

    public static function myPlayLog($uid,$page,$limit){
        $results = self::alias('a')
            ->join('video_play_log3 b','b.vid = a.id','inner')
            ->field(self::inter_field)
            ->where('b.uid','=',$uid)
            ->where('a.status','=',1)
            ->order('b.add_time desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
        return $results;
    }
}