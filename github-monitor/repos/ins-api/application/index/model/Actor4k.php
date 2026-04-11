<?php

namespace app\index\model;

use app\lib\exception\BaseException;
use think\cache\driver\Redis;
use think\Db;
use think\Model;

class Actor4k extends Model
{

    protected $table = 'actor_4k';
    const list_field = 'id,name,name_en,name_ru,image';
    const info_field = 'id,name,name_en,name_ru,image,video_count';
    protected $hidden = ['name_en','name_ru'];

    public function getNameAttr($val,$data){
        $lang = getLang();
        if ($lang == 'en' && !empty($data['name_en'])){
            return $data['name_en'];
        }

        if($lang == 'ru' && !empty($data['name_ru'])){
            return $data['name_ru'];
        }
        return $val;
    }

    // public function getImageAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }


    public function getIsSubscribeAttr($val,$data){
        $is_subscribe = 0;
        $count = ActorSubscribe4k::where('uid','=',getUid())->where('actor_id','=',$data['id'])->count();
        if($count){
            $is_subscribe = 1;
        }
        return $is_subscribe;
    }

    public static function lists($page,$limit,$order,$keyword){

        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'actor4k_list_'.$page.'_'.$limit.'_'.$order.'_'.$keyword.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $where = "is_show = 1 and video_count > 0";
            if($keyword){
                $keyword = trim($keyword);
                $where .= " and (instr(name, '".$keyword."') or instr(name_en, '".$keyword."') or instr(name_ru, '".$keyword."'))";
            }
            switch ($order){
                case 1: $order = 'play desc';break;
                case 2: $order = 'play_month desc';break;
                case 3: $order = 'play_week desc';break;
                case 4: $order = 'sort asc,play_week desc';break;
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 86400);;
        }
        return $results;
    }


    public static function getAid(){
        $aid = getInput('aid');
        $id = self::where('id','=',$aid)->value('id');
        if(!$id){
            throw new BaseException(3003);
        }
        return $id;
    }

    public static function info($aid){
        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'actor4k_info_'.$aid.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $results = self::field(self::info_field)
                ->where('id','=',$aid)->find();
            if(!$results) throw new BaseException(3003);
            $redis->set($redis_key, $results, 86400);;
        }
        return $results->append(['is_subscribe']);
    }

    public static function up_play($actor){
        $time = time();
        $play_time=self::where('id','=',$actor)->value('play_time');
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
        self::where('id','=',$actor)->update($data);
    }
}