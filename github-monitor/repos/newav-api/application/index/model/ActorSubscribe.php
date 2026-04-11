<?php

namespace app\index\model;
use think\Model;
use think\Db;

class ActorSubscribe extends Model
{


    public static function lists($uid,$page,$limit){

        return (new Actors)->alias('a')
            ->join('actor_subscribe b','b.actor_id = a.id','inner')
            ->field('a.id,a.name,a.name_en,a.name_ru,a.image,a.video_count,b.add_time')
            ->where('b.uid','=',$uid)
            ->order('b.add_time desc')
            ->paginate([
                'list_rows' => $limit,
                'page'     => $page,
            ]);
    }

    public static function subscribe($uid, $actor_id)
    {
        $exists = Db::name('actor_subscribe')
            ->where('uid', $uid)
            ->where('actor_id', $actor_id)
            ->find();

        if ($exists) {
            // Unsubscribe
            $success = Db::name('actor_subscribe')
                ->where('uid', $uid)
                ->where('actor_id', $actor_id)
                ->delete();
            return $success !== false ? false : null;
        } else {
            // Subscribe
            $success = Db::name('actor_subscribe')->insert([
                'uid'      => $uid,
                'actor_id' => $actor_id,
                'add_time' => time()
            ]);
            return $success ? true : null;
        }
    }
}