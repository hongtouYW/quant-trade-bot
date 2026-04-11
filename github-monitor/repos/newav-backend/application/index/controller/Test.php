<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/22
 * Time: 14:28
 */

namespace app\index\controller;

use app\index\model\Actor as ActorModel;
use app\index\model\Tags2;
use think\cache\driver\Redis;
use think\Controller;
use think\Db;

class Test extends Controller
{


    public function monthVideo(){

        $end = time();
        $start = strtotime('-1 month', $end);
        $where = [
            ['insert_time','>=',$start],
        ];
        $lists1  = \app\index\model\Video::field('video_url')->where($where)->select()->toArray();
        $lists2  = \app\index\model\Video4k::field('video_url')->where($where)->select()->toArray();
        $lists3  = \app\index\model\Comic::field('video_url')->where($where)->select()->toArray();
        $lists = array_merge($lists1,$lists2,$lists3);
        foreach ($lists as $k=>$v){
           echo $v['video_url'].'<br>';
        }

    }



    public function clearPartRedis(){

        $redis = new Redis();
        $part = input('part');
        if(!$part){
            exit('指定不能为空');
        };
        $keys = $redis->keys($part.'*');
        if($keys){
            $redis->del($keys);
        }
        exit('success');
    }


    public function test(){

        echo  '4-17-1';
    }

    public function is_4k(){
        exit();
        $tag_id = 7;

        $where = "is_4k = 0 and instr(CONCAT( ',', tags, ',' ),  ',".$tag_id.",' )";

         $data = [
            'is_4k'=>1,
            'source'=>1
        ];
        \app\index\model\Video4k::where($where)->update($data);

        exit('success');

    }

    public function actor(){

/*        exit();*/
        $min_id = input('min_id');
        $max_id = input('max_id');

        for ($i=$min_id;$i<=$max_id;$i+=1){
            $image = '/upload/img/avatar/'.$i.'.jpg';
            ActorModel::where('id','=',$i)->update(['image'=>$image]);
        }

        echo '操作成功';die();

    }


    public function actor_count(){
/*        exit('actor');*/
        set_time_limit(0);
        $list = \app\index\model\Actor::select();
        foreach ($list as $k=>$v){
            $update = [
                'video_count'=>\app\index\model\Video::where('actor','=',$v['id'])->where('mosaic','=',1)->where('status','=',1)->count(),
                'video_count1'=>\app\index\model\Video::where('actor','=',$v['id'])->where('mosaic','=',0)->where('status','=',1)->count(),
            ];
            \app\index\model\Actor::where('id','=',$v['id'])->update($update);
        }
        echo 'success';
    }


    public function actor4k_count(){
/*        exit('actor4k_count');*/
        $list = \app\index\model\Actor4k::select();
        foreach ($list as $k=>$v){
            $count = \app\index\model\Video4k::where('actor','=',$v['id'])->where('status','=',1)->count();
            \app\index\model\Actor4k::where('id','=',$v['id'])->update(['video_count'=>$count]);
        }
        echo 'success';
    }

    public function actor2_count(){
        $list = \app\index\model\Actor2::select();
        foreach ($list as $k=>$v){

            $count = \app\index\model\Comic::where('actor','=',$v['id'])->where('status','=',1)->count();
            \app\index\model\Actor2::where('id','=',$v['id'])->update(['video_count'=>$count]);
        }
        echo 'success';
    }

    public function tags2_count(){
        $list = Tags2::select();
        foreach ($list as $k=>$v){
            $where2 = "instr(CONCAT( ',', tags, ',' ),  ',".$v['id'].",' )";
            $count = \app\index\model\Comic::where($where2)->where('status','=',1)->count();
            Tags2::where('id','=',$v['id'])->update(['video_count'=>$count]);
        }
        echo 'success';
    }


    public function tags_count(){
/*        exit('tags');*/
        $list = \app\index\model\Tags::select();
        foreach ($list as $k=>$v){
            $where2 = "instr(CONCAT( ',', tags, ',' ),  ',".$v['id'].",' )";
            $update = [
                'video_count'=>\app\index\model\Video::where($where2)->where('mosaic','=',1)->where('status','=',1)->count(),
                'video_count1'=>\app\index\model\Video::where($where2)->where('mosaic','=',0)->where('status','=',1)->count(),
            ];
            \app\index\model\Tags::where('id','=',$v['id'])->update($update);
        }
        exit('success');
    }


    public function tag4k_count(){
        /*        exit('tag4k');*/
        $list = \app\index\model\Tag4k::select();
        foreach ($list as $k=>$v){
            $where2 = "instr(CONCAT( ',', tags, ',' ),  ',".$v['id'].",' )";
            $update = [
                'video_count'=>\app\index\model\Video4k::where($where2)->where('status','=',1)->count(),
            ];
            \app\index\model\Tag4k::where('id','=',$v['id'])->update($update);
        }
        echo 'success';
    }

    public function is_handle(){

        exit('handle');
        $list = Db::name('comic')->field('id,video_url')->select();


        foreach ($list as $k=>$v){

            $res = strpos($v['video_url'],'m3u8');
            if($res){
                Db::name('comic')->where('id','=',$v['id'])->setField('is_handle',1);
            }
        }

        exit('success');

    }

    public function publisher_count(){
/*        exit('publisher');*/
        $list = \app\index\model\Publisher::select();
        foreach ($list as $k=>$v){
            $update = [
                'video_count'=>\app\index\model\Video::where('publisher','=',$v['id'])->where('mosaic','=',1)->where('status','=',1)->count(),
                'video_count1'=>\app\index\model\Video::where('publisher','=',$v['id'])->where('mosaic','=',0)->where('status','=',1)->count(),
                'video_count2'=>\app\index\model\Video4k::where('publisher','=',$v['id'])->where('status','=',1)->count()
            ];
            \app\index\model\Publisher::where('id','=',$v['id'])->update($update);
        }
        exit('success');
    }
}