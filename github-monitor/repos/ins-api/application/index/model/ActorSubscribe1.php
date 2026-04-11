<?php

namespace app\index\model;
use think\Model;
class ActorSubscribe1 extends Model
{

    public static function lists($uid,$page,$limit){

        return (new Actors)->alias('a')
            ->join('actor_subscribe1 b','b.actor_id = a.id','inner')
            ->field('a.id,a.name,a.name_en,a.name_ru,a.image,a.video_count,video_count1,b.add_time')
            ->where('b.uid','=',$uid)
            ->order('b.add_time desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
    }
}