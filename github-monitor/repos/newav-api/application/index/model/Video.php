<?php

namespace app\index\model;
use think\cache\driver\Redis;
use think\Db;
use think\Model;
use app\lib\exception\BaseException;


class Video extends Model
{
    protected $table = 'video';

    const list_field = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,mash,duration,actor,director,publisher,publish_date,thumb,preview,private,play,play_day,play_week,play_month,subtitle,rating_avg,rating_count,position,zimu_status';
    const info_field = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,mash,duration,tags,actor,director,thumb,thumb_series,preview,panorama,video_url,description,description_zh,description_en,description_ru,description_ms,description_th,description_es,private,play,publisher,publish_date,subtitle,zimu_status,zimu,zimu_zh,zimu_en,zimu_ru,zimu_ms,zimu_th,zimu_es,rating_avg,rating_count,position';
    const inter_field = 'a.id,a.title,a.title_zh,a.title_en,a.title_ru,a.title_ms,a.title_th,a.title_es,a.mash,a.actor,a.director,a.publisher,a.publish_date,a.duration,a.thumb,a.preview,a.private,a.play,a.play_day,a.play_week,a.play_month,a.subtitle,a.rating_avg,a.rating_count,a.position,a.zimu_status';

    protected $hidden = ['title_zh','title_en','title_ru','title_ms','title_th','title_es','description_zh','description_en','description_ru','description_ms','description_th','description_es','zimu_zh','zimu_en','zimu_ru','zimu_ms','zimu_th','zimu_es'];
    // protected $append = ['collect_count', 'video_point', 'mash_title','is_new', 'is_purchase'];
    protected $append = ['video_point', 'mash_title','is_new'];

    public function getIsPurchaseAttr($val,$data){
        $is_purchase = 0;
        $uid         = getUid();
        $count       = VideoPurchase::hasPurchased($uid,$data['id']);

        if($count){
            $is_purchase = 1;
        }
        return $is_purchase;
    }

    public function getIsNewAttr($val, $data)
    {
        if (empty($data['publish_date'])) {
            return 0;
        }
        
        // Convert dates to timestamps for proper comparison
        $day              = Configs::get('new_video');
        $publishTimestamp = strtotime($data['publish_date']);
        $currentTimestamp = time();
        $sevenDaysAgoTimestamp = strtotime('-'.$day.' days');
        
        // Check if publish_date is within the last 7 days
        return ($publishTimestamp >= $sevenDaysAgoTimestamp && $publishTimestamp <= $currentTimestamp) ? 1 : 0;
    }

    public function getMashTitleAttr($val, $data)
    {
        $mash  = isset($data['mash']) ? $this->getMashAttr($data['mash']) : '';
        $title = isset($data['title']) ? $this->getTitleAttr($data['title'], $data) : '';
        return trim($mash . ' ' . $title);
    }

    public function getVideoPointAttr($val, $data)
    {
        return Configs::get('video_point');
    }

    public function getIsCollectAttr($val,$data){
        $is_collect = 0;
        $uid        = getUid();
        if ($uid) {
            $query = VideoCollect::where('uid', '=', $uid)->where('vid', '=', $data['id']);
            $count = $query->count();
            if($count){
                $is_collect = 1;
            }
        }
        return $is_collect;
    }

    public function getIsSubscribeAttr($val,$data){
        $is_subscribe = 0;
        $uid          = getUid();
        $count = ActorSubscribe::where('uid','=', $uid)->where('actor_id','=',$data['actor'])->count();
        if($count){
            $is_subscribe = 1;
        }
        return $is_subscribe;
    }

    public function getTitleAttr($val,$data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'title',
            'en' => 'title_en',
            'ru' => 'title_ru',
            'ms' => 'title_ms',
            'th' => 'title_th',
            'es' => 'title_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getDescriptionAttr($val, $data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'description',
            'en' => 'description_en',
            'ru' => 'description_ru',
            'ms' => 'description_ms',
            'th' => 'description_th',
            'es' => 'description_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getActorAttr($value)
    {
        if (empty($value)) {
            return [];
        }

        $ids = explode(',', $value);
        $lang = strtolower(getLang());

        // Map language to actor name fields
        $langMap = [
            'zh' => 'name',
            'en' => 'name_en',
            'ru' => 'name_ru',
            'ms' => 'name_ms',
            'th' => 'name_th',
            'es' => 'name_es',
        ];

        // Pick correct column, fallback to `name`
        $nameField = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
        $list = Actors::whereIn('id', $ids)->field("id, {$nameField} as name, image")->select();
        return $list ? $list->toArray() : [];
    }

    public function getPublisherAttr($val){
        if ($val) {
            $lang = strtolower(getLang());
            $langMap = [
                'zh' => 'name',
                'en' => 'name_en',
                'ru' => 'name_ru',
                'ms' => 'name_ms',
                'th' => 'name_th',
                'es' => 'name_es',
            ];

            $nameField = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
            $data      = Publisher::where('id', $val)->field("id, {$nameField} as name, image")->find();
            return $data ? $data : null;
        }
        return null;
    }

    public function getTagsAttr($value){
        if (empty($value)) {
            return [];
        }

        $ids = explode(',', $value);
        $lang = strtolower(getLang());

        // Map language to tag name fields
        $langMap = [
            'zh' => 'name',
            'en' => 'name_en',
            'ru' => 'name_ru',
            'ms' => 'name_ms',
            'th' => 'name_th',
            'es' => 'name_es',
        ];

        // Pick correct column, fallback to `name`
        $nameField = isset($langMap[$lang]) ? $langMap[$lang] : 'name';
        $list = Tags::whereIn('id', $ids)->field("id, {$nameField} as name, image")->select();
        return $list ? $list->toArray() : [];
    }

    public function getCollectCountAttr($val,$data){
        $count =  VideoCollect::where('vid','=',$data['id'])->count();
        return formatNumber($count);
    }

    public function getVideoGroupAttr($val, $data){
        $result = null;
        if ($data['private'] == 3) {
            $lang = strtolower(getLang());
            $langMap = [
                'zh' => 'title',
                'en' => 'title_en',
                'ru' => 'title_ru',
                'ms' => 'title_ms',
                'th' => 'title_th',
                'es' => 'title_es',
            ];
            $titleField = isset($langMap[$lang]) ? $langMap[$lang] : 'title';

            $result = VideoGroupDetails::alias('vgd')
                ->join('video_groups vg', 'vg.id = vgd.group_id')
                ->where('vgd.video_id', '=', $data['id'])
                ->field("vg.id, vg.{$titleField} as title")
                ->select()
                ->toArray();
        }
        return $result;
    }

    public function getPlayAttr($val,$data){
        return formatNumber($val);
    }

    public function getPlayDayAttr($val,$data){
        return formatNumber($val);
    }

    public function getPlayWeekAttr($val,$data){
        return formatNumber($val);
    }

    public function getPlayMonthAttr($val,$data){
        return formatNumber($val);
    }

    // public function getPublisherNameAttr($val,$data){ // duplicate with getPublisherAttr 10/13/2025
    //     $data = Publisher::field('name')->where('id','=',$data['publisher'])->find();
    //     return $data['name'];
    // }

    public function getThumbAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getPreviewAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getZimuAttr($val, $data)
    {
        $zimuUrl = Configs::get('zimu_url');
        $langMap = [
            'or' => 'zimu',
            'en' => 'zimu_en',
            'zh' => 'zimu_zh',
            'ru' => 'zimu_ru',
            'ms' => 'zimu_ms',
            'th' => 'zimu_th',
            'es' => 'zimu_es',
        ];

        $result = [];

        foreach ($langMap as $lang => $field) {
            $url = null;

            if (!empty($data[$field])) {
                $url = $zimuUrl . '/' . ltrim($data[$field], '/');

                // Insert the video folder name into the subtitles path
                if (preg_match('#/(subtitles)/(ru|en|zh|ms|th|es)/#', $url, $matches)) {
                    if (preg_match('#/([a-z0-9]+__\d+)/subtitles/#i', $url, $videoMatches)) {
                        $videoId = $videoMatches[1];
                        $url = preg_replace(
                            '#/subtitles/(' . $matches[2] . ')/#',
                            '/subtitles/' . $videoId . '/' . $matches[2] . '/',
                            $url
                        );
                    }
                }
            // } else {
            //     // default fallback
            //     $defaultFonts = [
            //         'en' => 'font/default_en.vtt',
            //         'ru' => 'font/default_ru.vtt',
            //     ];
            //     $url = $zimuUrl . '/' . ($defaultFonts[$lang] ?? 'font/default.vtt');
            }

            $result[$lang] = $url;
        }

        return $result;
    }

    public function getPanoramaAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getMashAttr($val){
        $lang = getLang();
        if(empty($val)){
            if ($lang == 'en')
                // $val = 'No mosaic';
                $val = '';
            else if ($lang == 'ru')
                // $val = 'Нет мозаики';
                $val = '';
            else
                // $val = '暂无番号';
                $val = '';
        }
        return $val;
    }

    public function getVideoUrlAttr($val){
        if(!empty($val)){
            $video_url = Configs::get('video_url');
            $val = $video_url.$val;
        }
        return $val;
    }

    public function getReviewsAttr($value, $data)
    {
        return Rating::alias('r')
            ->join('user u', 'r.user_id = u.id')
            ->where('r.video_id', $data['id'])
            ->where('r.status', 1)
            ->field('u.username, r.rating, r.review, r.created_at')
            ->order('r.created_at', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
    }

    private static function jianquan($url){
        $parse      = parse_url($url);
        $expiryTime = 7200; // 有效期（秒）
        $secretKey  = 'FoEDb2QIeVvUOyTlBJ9NMDYgJFNZ30';
        $wstime     = time() + $expiryTime; // 当前时间戳 + 有效期
        $uri        = $parse['path']; // 资源路径
        $group      = $secretKey . $uri . $wstime; // 生成鉴权组合：密钥 + 路径 + 时间戳
        $wsSecret   = md5($group); // 使用 MD5 加密生成签名
        return $url."?wsSecret=" . $wsSecret . "&wsTime=" . $wstime;
    }

    public function getDurationAttr($val){
        return secondsToHourMinute($val);
    }
    /**
     * 获取评分平均值，如果为0则生成随机评分
     */
    public function getRatingAvgAttr($val, $data)
    {
        // 检查当前评分平均值和评分数量
        $currentRatingAvg = $val;
        $currentRatingCount = isset($data['rating_count']) ? $data['rating_count'] : 0;
        
        // 如果评分平均值和评分数量都为0，生成随机评分
        if (empty($currentRatingAvg) && empty($currentRatingCount)) {
            // 生成 4.5 到 4.9 之间的随机评分，保留1位小数
            $randomRating = mt_rand(45, 49) / 10;
            return $randomRating;
        }
        
        return $currentRatingAvg;
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
        $lang = getLang();
        $redis_key = 'video_info_' . $vid.'_'.$lang;
        $results = $redis->get($redis_key);
        if (!$results) {
            $results = self::field(self::info_field)
            ->where('id','=', $vid)->find();
            if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        if (!$results) throw new BaseException(3001);
        return $results->append(['is_collect','is_subscribe','reviews','is_purchase','collect_count','video_group']);
    }

    // public static function video_url123($vid){ // had update a new video_url function
    //     $info = self::info($vid);
    //     $uid  = getUid();
    //     if($info['private'] == 1){
    //         if(!$uid){
    //             throw new BaseException(2000);
    //         }
    //     }else if($info['private'] == 2){
    //         if(!$uid){
    //             throw new BaseException(2000);
    //         }
    //         $is_vip =User::is_vip($uid);
    //         if(!$is_vip){
    //             throw new BaseException(3004);
    //         }
    //     }

    //     $redis_key = 'video_url_'.$vid;
    //     $redis     = new Redis();
    //     $url       = $redis->get($redis_key);
    //     if(!$url){
    //         $url = self::jianquan($info['video_url']);
    //         $redis->set($redis_key, $url, 300); //5分钟
    //     }

    //     //视频增加观看次数
    //     self::setPlay($vid);
    //     if($uid){ 
    //         VideoPlayLog::add_log($uid,$vid);
    //     }
    //     if(!empty($info['actor'])){
    //         foreach($info['actor'] as $actor){
    //             Actors::up_play($actor['id']);
    //         }
    //     }
    //     return $url;
    // }

    public static function video_url($vid)
    {
        $info = self::info($vid);
        $uid  = getUid();

        if ($info['private'] == 3) { 
            $hasAccess = VideoGroupDetails::getVideoGroup($uid, $vid);
            if (!$hasAccess) {
                throw new BaseException(5007);
                // throw new BaseException(['code' => 5007, 'msg' => "User haven't purchase the video group"]);
            }
        } else {
            $hasAccess = User::hasVideoAccess($uid, $info);
            if (!$hasAccess) {
                if (!$uid) {
                throw new BaseException(6007);
                    // throw new BaseException(['code' => 6007, 'msg' => "User haven't purchase the video"]);
                }
                $user = User::where('id', $uid)->find();
                if ($user && !empty($user['vip_end_time']) && time() > $user['vip_end_time']) {
                    throw new BaseException(7001);
                    // throw new BaseException(['code' => 7001, 'msg' => "VIP has expired"]);
                }
                throw new BaseException(6008);
                // throw new BaseException(['code' => 6008, 'msg' => "User haven't purchase the video or VIP"]);
            }
        }

        $redis_key = 'video_url_' . $vid;
        $redis     = new Redis();
        $url       = $redis->get($redis_key);
        if (!$url) {
            $url = self::jianquan($info['video_url']);
            $redis->set($redis_key, $url, 300); // 5 minutes
        }

        self::setPlay($vid);
        if ($uid) {
            VideoPlayLog::add_log($uid, $vid);
        }

        if (!empty($info['actor'])) {
            foreach ($info['actor'] as $actor) {
                Actors::up_play($actor['id']);
            }
        }
        return $url;
    }
    public static function lists($page,$limit,$tag_id,$actor_id,$publisher_id,$keyword,$type,$publish_date,$private,$random=null,$sequel=null){
        $redis      = new Redis();
        $delayHours = Configs::get('video_delay_hours');
        $lang       = getLang();
        $redis_key  = 'video_list_'.$tag_id.'_'.$actor_id.'_'.$publisher_id.'_'.$keyword.'_'.$type.'_'.$page.'_'.$limit.'_'.$lang.'_'.$publish_date.'_'.$private.'_'.$random.'_'.$sequel;
        $results    = $redis->get($redis_key);
        if($random == 1){
            $results = null;
        }
        if(!$results){
            $where = [];
            $whereRaw = [];
            $order = ($sequel == 1) ? 'mash asc' : 'update_time desc';
            if ($random == 1) {
                $order = 'rand()';
            }
            if($publisher_id){
                $where[] = ['publisher','=',$publisher_id];
            }
            if($private){
                $where[] = ['private','=',$private];
            }
            
            // Handle publish_date with mixed formats
            if ($publish_date) {
                if ($publish_date === 'early_video') {
                    // Early videos
                    $whereRaw[] = "(STR_TO_DATE(publish_date, '%Y-%m-%d') < '2016-01-01' 
                                OR STR_TO_DATE(publish_date, '%Y%m%d') < '2016-01-01' 
                                OR (publish_date REGEXP '^[0-9]+$' AND FROM_UNIXTIME(publish_date) < '2016-01-01'))";
                    
                } else {
                    // Normal year-month filter
                    $startDate = date('Y-m-01', strtotime($publish_date));
                    $endDate   = date('Y-m-t', strtotime($publish_date));
                    // due 3rd party may pass timestamp/date format
                    $whereRaw[] = "(STR_TO_DATE(publish_date, '%Y-%m-%d') BETWEEN '$startDate' AND '$endDate'
                                OR STR_TO_DATE(publish_date, '%Y%m%d') BETWEEN '$startDate' AND '$endDate'
                                OR (publish_date REGEXP '^[0-9]+$' AND FROM_UNIXTIME(publish_date) BETWEEN '$startDate' AND '$endDate'))";
                }
            }
            
            switch ($type){
                case 1:$where[] = ['recommend','=',1];break;
                case 2:$where[] = ['private','=',2];break;
                case 3:$where[] = ['private','=',0];break;
                case 4:$where[] = ['subtitle','=','1'];break;
                default:
                    break;
            }
            
            // Build conditions
            $delayTimestamp = time() - ($delayHours * 3600);
            $where[] = ['insert_time', '<=', $delayTimestamp];
            
            // Start with status condition
            $rawConditions = ["status = 1"];
            
            if($tag_id){
                $rawConditions[] = "instr(CONCAT( ',', tags, ',' ),  ',".$tag_id.",' )";
            }
            if($actor_id){
                $rawConditions[] = "instr(CONCAT( ',', actor, ',' ),  ',".$actor_id.",' )";
            }
            if($keyword){
                $keyword = preg_replace("/([a-zA-Z]+)\s*(\d+)/", "$1-$2", $keyword);
                $rawConditions[] = "(instr(title, '".$keyword."') 
                                or instr(title_zh, '".$keyword."') 
                                or instr(title_twzh, '".$keyword."') 
                                or instr(title_en, '".$keyword."') 
                                or instr(title_ru, '".$keyword."') 
                                or instr(mash, '".$keyword."'))";
            }
            
            // Merge publish_date conditions with other raw conditions
            $allRawConditions = array_merge($whereRaw, $rawConditions);
            $whereRawString   = implode(' AND ', $allRawConditions);
            
            // Build query
            $query = self::field(self::list_field)->where($where);
            if (!empty($whereRawString)) {
                $query = $query->whereRaw($whereRawString);
            }
            $results = $query->orderRaw($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
                
            if ($results['data']) $redis->set($redis_key, $results, 7200);
        }
        foreach ($results['data'] as &$video) {
            $videoModel = new self($video);
            $video['is_purchase']   = $videoModel->getIsPurchaseAttr(null, $video);
            $video['is_collect']    = $videoModel->getIsCollectAttr(null, $video);
            $video['collect_count'] = $videoModel->getCollectCountAttr(null, $video);
        }
        return $results;
    }


    public static function hotLists($page,$limit,$order){
        $redis      = new Redis();
        $delayHours = Configs::get('video_delay_hours');
        $lang       = getLang();
        $redis_key  = 'video_list_hot_'.$order.'_'.$page.'_'.$limit.'_'.$lang;
        $results    = $redis->get($redis_key);
        if(!$results){
            $where          = [];
            $delayTimestamp = time() - ($delayHours * 3600); // only get the video insert time more than $delayHours
            $where[]        = ['insert_time', '<=', $delayTimestamp];
            $where[]        = ['status','=',1];

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
        foreach ($results['data'] as &$video) {
            $videoModel = new self($video);
            $video['is_purchase']   = $videoModel->getIsPurchaseAttr(null, $video);
            $video['is_collect']    = $videoModel->getIsCollectAttr(null, $video);
            $video['collect_count'] = $videoModel->getCollectCountAttr(null, $video);
        }
        return $results;
    }

    public static function indexLists($page,$limit,$type){
        $redis      = new Redis();
        $delayHours = Configs::get('video_delay_hours');
        $lang       = getlang();
        $redis_key  = 'video_list_index_'.$type.'_'.$page.'_'.$limit.'_'.$lang;
        $results    = $redis->get($redis_key);
        $order      = 'play_week desc,update_time desc';
        if(!$results){
            $where          = [];
            $delayTimestamp = time() - ($delayHours * 3600); // only get the video insert time more than $delayHours
            $where[]        = ['insert_time', '<=', $delayTimestamp];
            $where[]        = ['status','=',1];

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
        foreach ($results['data'] as &$video) {
            $videoModel = new self($video);
            $video['is_purchase']   = $videoModel->getIsPurchaseAttr(null, $video);
            $video['is_collect']    = $videoModel->getIsCollectAttr(null, $video);
            $video['collect_count'] = $videoModel->getCollectCountAttr(null, $video);
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
            ->join('video_collect b','b.vid = a.id','inner')
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
            ->join('video_play_log b','b.vid = a.id','inner')
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

    public static function myPurchase($uid, $page = 1, $limit = 20){
        $videoIds = Db::name('video_purchases')
            ->where('uid', $uid)
            ->order('purchased_at', 'desc')
            ->page($page, $limit)
            ->column('video_id');

        // Fetch the group data using your defined fields
        $videos = self::field(self::list_field)
            ->where('id', 'in', $videoIds)
            ->where('status', 1)
            ->select()
            ->toArray();

        // Get total count
        $total = Db::name('video_purchases')->where('uid', $uid)->count();
        return [
            'data'         => $videos,
            'total'        => $total,
            'per_page'     => $limit,
            'current_page' => $page
        ];
    }

    public static function getRelatedVideos($vid, $page, $limit, $related_by)
    {
        $delayHours     = Configs::get('video_delay_hours');
        $delayTimestamp = time() - ($delayHours * 3600);
        $current        = self::where('id', $vid)->where('status', 1)->find();
        $where          = [];

        // extract actor (first actor only)$firstActor = null;
        $rawActor = $current->getData('actor'); // raw string from DB

        if (!empty($rawActor)) {
            $actorValue = trim($rawActor);
            $actors = explode(',', $actorValue);
            $firstActor = intval($actors[0]);
        }

        // extract mash prefix
        $mashPrefix = null;
        if (!empty($current['mash'])) {
            $mashPrefix = explode('-', $current['mash'])[0];
        }

        // Build filter based on related_by
        if ($related_by == 1 && $firstActor) {
            $where[] = function($query) use ($firstActor) {
                $query->whereRaw("FIND_IN_SET(?, actor)", [$firstActor]);
            };
        } elseif ($related_by == 2 && $current['publisher']) {
            $where[] = ['publisher', '=', $current['publisher']];
        } elseif ($related_by == 3 && $mashPrefix) {
            $where[] = ['mash', 'like', $mashPrefix . '%'];
        }

        $where[] = ['id', '<>', $vid];
        $where[] = ['insert_time', '<=', $delayTimestamp];
        $where[] = ['status', '=', 1];
        $query   = self::where($where);
        $main    = $query->orderRaw('rand()')
            ->paginate([
                'list_rows' => $limit,
                'page' => $page,
            ])
            ->toArray();

        $data = $main['data'];

        // If not enough data fill with random
        if (count($data) < $limit) {
            $need           = $limit - count($data);
            $idsToExclude   = array_column($data, 'id');
            $idsToExclude[] = $vid;

            $random = self::where('id', 'not in', $idsToExclude)
                ->where('status', 1)
                ->where('insert_time', '<=', $delayTimestamp)
                ->orderRaw('rand()')
                ->limit($need)
                ->select()
                ->toArray();

            $data = array_merge($data, $random);
        }

        foreach ($data as &$video) {
            $vm = new self($video);
            $video['is_purchase']   = $vm->getIsPurchaseAttr(null, $video);
            $video['is_collect']    = $vm->getIsCollectAttr(null, $video);
            $video['collect_count'] = $vm->getCollectCountAttr(null, $video);
        }
        $main['data'] = $data;
        return $main;
    }

    public static function getUserRecommendedVideos($uid, $page, $limit, $by)
    {
        // Build fallback order dynamically
        $methods  = [$by, 1, 2, 3, 4, 5];
        $methods  = array_values(array_unique($methods)); // remove duplicates
        $finalIds = [];
        $remain   = $limit;

        foreach ($methods as $method) {
            if ($remain <= 0) break;
            $videos = self::getVideosByMethod($uid, $method, $remain, $finalIds);
            if (!empty($videos)) {
                foreach ($videos as $v) {
                    $finalIds[] = $v['id'];
                }
            }
            $remain = $limit - count($finalIds);
        }

        // Still not enough → use random fallback via lists()
        if ($remain > 0) {
            $extra = self::lists(1, $remain, null, null, null, null, null, null, null, 1, 0);
            if (!empty($extra['data'])) {
                foreach ($extra['data'] as $v) {
                    if (!in_array($v['id'], $finalIds)) {
                        $finalIds[] = $v['id'];
                    }
                }
            }
        }

        // fetch final list in pagination format, same as lists()
        $final = self::whereIn('id', $finalIds)
            ->orderRaw("rand()")
            ->paginate([
                'list_rows' => $limit,
                'page'      => $page
            ])
            ->toArray();

        return $final;
    }

    private static function getVideosByMethod($uid, $method, $limit, $excludeIds = [])
    {
        $query = self::where('status', 1);

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }
        switch ($method) {
            case 1: // subscribed actors
                $actorIds = Db::name('actor_subscribe')
                    ->where('uid', $uid)
                    ->column('actor_id');
                if (empty($actorIds)) return [];
                $query->where(function ($q) use ($actorIds) {
                    foreach ($actorIds as $id) {
                        $q->whereOr("FIND_IN_SET($id, actor)");
                    }
                });
                break;
            case 2: // subscribed publishers
                $publisherIds = Db::name('publisher_subscribe')
                    ->where('user_id', $uid)
                    ->column('publisher_id');
                if (empty($publisherIds)) return [];
                $query->whereIn('publisher', $publisherIds);
                break;
            case 3: // last collect
                $lastCollectVid = Db::name('video_collect')
                    ->where('uid', $uid)
                    ->order('add_time DESC')
                    ->value('vid');
                if (!$lastCollectVid) return [];
                return self::getRelatedVideos($lastCollectVid, 1, $limit, 1)['data'] ?? [];

            case 4: // last play
                $lastPlayVid = Db::name('video_play_log')
                    ->where('uid', $uid)
                    ->order('add_time DESC')
                    ->value('vid');
                if (!$lastPlayVid) return [];
                return self::getRelatedVideos($lastPlayVid, 1, $limit, 1)['data'] ?? [];

            case 5: // random fallback
            default:
                return self::lists(1, $limit, null, null, null, null, null, null, null, 1, 0)['data'] ?? [];
        }
        return $query
            ->orderRaw("rand()")
            ->limit($limit)
            ->select()
            ->toArray();
    }
}