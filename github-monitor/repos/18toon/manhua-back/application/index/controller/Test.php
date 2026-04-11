<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/11/22
 * Time: 14:28
 */

namespace app\index\controller;
use app\index\model\Chapter;
use app\index\model\Comic as ComicModel;
use think\cache\driver\Redis;
use think\Controller;
use think\Db;

class Test extends Controller
{
    

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

        echo  '11-26';
    }

    public function async_chapter(){
        exit('禁止访问');
        set_time_limit(0);

        $where = [];
/*        $where[] = ['status','eq',1];
        $where[] = ['mhstatus','eq',0];*/
/*        $where[] = ['cjname','=','jmtt'];*/
        $lists = ComicModel::field('id,cjid as book_id,last_chapter as section_id')->where($where)->order('id desc')->select();
        foreach ($lists as $k=>$v) {
            $last_info = Db::name('capter')->field('cjid,title,update_time')->where('manhua_id','=',$v['id'])->order('sort desc')->limit(1)->find();
            if ($last_info) {
                $update = [
                    'last_chapter' => $last_info['cjid'],
                    'last_chapter_title' => $last_info['title'],
                    'update_time' => $last_info['update_time'],
                ];
                ComicModel::where('id','=',$v['id'])->update($update);
                echo $v['id'].'更新成功';
                echo '<br>';
            }
        }
    }
}