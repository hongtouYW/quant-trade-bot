<?php

namespace app\index\model;
use think\Model;
class ActorSubscribe4k extends Model
{

    public static function lists($uid,$page,$limit){

        return (new Actor4k)->alias('a')
            ->join('actor_subscribe4k b','b.actor_id = a.id','inner')
            ->field('a.id,a.name,a.name_en,a.name_ru,a.image,a.video_count,b.add_time')
            ->where('b.uid','=',$uid)
            ->order('b.add_time desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
    }

}