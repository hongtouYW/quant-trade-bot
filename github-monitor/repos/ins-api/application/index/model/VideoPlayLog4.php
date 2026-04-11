<?php

namespace app\index\model;

use think\Model;

class VideoPlayLog4 extends Model
{
    public static function add_log($uid,$vid){

        $is_play_id = self::where('uid','=',$uid)->where('vid','=',$vid)->value('id');
        $time = time();
        if($is_play_id){
            self::where('id','=',$is_play_id)->setField('add_time',$time);
        }else{
            $data = [
                'uid'=>$uid,
                'vid'=>$vid,
                'add_time'=>$time
            ];
            self::insert($data);
        }
    }
}