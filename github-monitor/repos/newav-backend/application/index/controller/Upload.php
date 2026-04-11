<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/24
 * Time: 14:02
 */

namespace app\index\controller;

use app\index\model\Actor4k as Actor4kModel;
use Overtrue\Pinyin\Pinyin;
use think\Controller;
use app\index\model\Video as VideoModel;
use app\index\model\Video4k as Video4KModel;
use app\index\model\Comic as ComicModel;
use app\index\model\Actor as ActorModel;
use app\index\model\Actor2 as Actor2Model;
use app\index\model\Publisher as PublisherModel;
use app\index\model\Tags as TagsModel;
use app\index\model\Tags2 as TagsModel2;
class Upload extends Controller
{


    public function del_video(){
        exit('禁止访问');
        if (\request()->isPost()) {

            $param = $this->request->param('id');//获取所有参数，最全
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = 'id不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $data['code'] = 0;

            $res = VideoModel::where('id','in',$param)->delete();
            if($res !== 'false'){

                $data=['code'=>1,'msg'=>'删除视频成功','id'=>$param];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            $data['msg'] = '删除视频失败';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));

        }

        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));
    }


    public function video(){

        exit('禁止访问');
        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            save_log($param, "param");
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = '请传入视频信息';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_key')){
                $data['msg'] = 'Api密钥为空/密钥错误';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['title'])){
                $data['msg'] = '标题不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['mash'])){
                $data['msg'] = '番号不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['video_url'])){
                $data['msg'] = '播放地址不能为空';
                save_log($data, 'post_error');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $data['video_url'] = $param['video_url'];
            if(empty($param['thumb'])){
                $data['msg'] = '封面不能为空';
                save_log($data, 'post_error');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['preview'])){
                $data['msg'] = '预览图不能为空';
                save_log($data, 'post_error');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

/*            if(empty($param['panorama'])){
                $data['msg'] = '全景长图不能为空';
                save_log($data, 'post_error');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }*/
            $panorama = !empty(trim($param['panorama']))?trim($param['panorama']):'';
            if(empty($param['publish_date'])){
                $data['msg'] = '发行时间不能为空';
                save_log($data, 'post_error');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $param['actor'] = !empty($param['actor'])?explode(',',trim($param['actor']))[0]:'佚名';
            $param['zimu'] = !empty($param['zimu'])?trim($param['zimu']):'';
            $actor = ActorModel::where('name','=',$param['actor'])->value('id');
            if (!$actor) {//如果演员不存在
                $actor = ActorModel::insertGetId(['name' => $param['actor']]);
            }
            $param['publisher'] = !empty($param['publisher'])?trim($param['publisher']):'佚名';
            $publisher = PublisherModel::where('name', '=',$param['publisher'])->value('id');
            if (!$publisher) {//如果演员不存在
                $publisher = PublisherModel::insertGetId(['name' => $param['publisher']]);
            }

            if(!empty($param['tags'])){
                $tags = $this->getTags($param['tags']);
            }
            $description = !empty(trim($param['description']))?trim($param['description']):'';
            $subtitle = !empty($param['subtitle'])?(int)$param['subtitle']:0;
            $mosaic = !empty($param['mosaic'])?(int)$param['mosaic']:0;

            if(in_array($param['private'],['0','1'],true)){
                $private = $param['private'];
            }else{
                $private = $param['private'];
            }

            $videoData = [
                'title'=>trim($param['title']),
                'mash'=>trim($param['mash']),
                'tags'=>$tags,
                'actor'=>$actor,
                'thumb'=>$param['thumb'],
                'preview'=>$param['preview'],
                'panorama'=>$panorama,
                'description'=>trim($description),
                'video_url'=>$param['video_url'],
                'private'=>$private,
                'publish_date'=>$param['publish_date'],
                'publisher'=>$publisher,
                'subtitle'=>$subtitle,
                'mosaic'=>$mosaic,
                'zimu'=>$param['zimu'],
                'insert_time'=>time()
            ];
            $vid = VideoModel::where('video_url','=',$param['video_url'])->value('id');
            if($vid){
                $up_id = VideoModel::where('id','=',$vid)->update($videoData);
                if($up_id === false){
                    $data['msg'] = '修改视频失败';
                    save_log($data, 'post_error');
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }

                $data=['code'=>1,'msg'=>'修改视频成功','video_url'=>$data['video_url']];
                save_log($data, 'update_success');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }else{
                $identifier = $param['identifier'];
                if(empty($identifier)){
                    $identifier = $this->convert($param['mash']) . md5(time() . mt_rand(1, 1000000));
                }
                if($mosaic == 0){
                    $videoData['status'] = 0;
                }
                $videoData['identifier'] = $identifier;
                $videoData['update_time'] = strtotime($param['publish_date']);
                $add_id = VideoModel::insert($videoData);

                if(!$add_id){
                    $data['msg'] = '插入视频失败';
                    save_log($data, 'post_error');
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }
                $data=['code'=>1,'msg'=>'插入视频成功','video_url'=>$data['video_url']];
                save_log($data, 'add_success');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

        }

        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));

    }





    public function video_4k(){
        exit('禁止访问');
        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            save_log($param, "param_4k");
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = '请传入视频信息';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_key_4k')){
                $data['msg'] = 'Api密钥为空/密钥错误';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['title'])){
                $data['msg'] = '标题不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['mash'])){
                $data['msg'] = '番号不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['video_url'])){
                $data['msg'] = '播放地址不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $data['mash'] = $param['mash'];
            if(empty($param['thumb'])){
                $data['msg'] = '封面不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['preview'])){
                $data['msg'] = '预览图不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $panorama = !empty(trim($param['panorama']))?trim($param['panorama']):'';
            if(empty($param['publish_date'])){
                $data['msg'] = '发行时间不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $param['actor'] = !empty($param['actor'])?explode(',',trim($param['actor']))[0]:'佚名';
            $param['zimu'] = !empty($param['zimu'])?trim($param['zimu']):'';
            $actor = Actor4kModel::where('name','=',$param['actor'])->value('id');
            if (!$actor) {//如果演员不存在
                $actor = Actor4kModel::insertGetId(['name' => $param['actor']]);
            }
            $param['publisher'] = !empty($param['publisher'])?trim($param['publisher']):'佚名';
            $publisher = PublisherModel::where('name', '=',$param['publisher'])->value('id');
            if (!$publisher) {//如果演员不存在
                $publisher = PublisherModel::insertGetId(['name' => $param['publisher']]);
            }

            $description = !empty(trim($param['description']))?trim($param['description']):'';
            $videoData = [
                'title'=>trim($param['title']),
                'mash'=>trim($param['mash']),
                'tags'=>7,
                'actor'=>$actor,
/*                'thumb'=>$param['thumb'],
                'preview'=>$param['preview'],*/
/*                'panorama'=>$panorama,*/
                'description'=>trim($description),
/*                'video_url'=>$param['video_url'],*/
                'private'=>2,
                'publish_date'=>$param['publish_date'],
                'publisher'=>$publisher,
                'mosaic'=>1,
                'is_4k'=>1,
                'status'=>0,
                'insert_time'=>time()
            ];
            $vid = Video4KModel::where('mash','=',$param['mash'])->where('source','=',0)->value('id');
            if($vid){
/*                $up_id = Video4KModel::where('id','=',$vid)->update($videoData);
                if($up_id === false){
                    $data['msg'] = '修改视频失败';
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }*/

                $data=['code'=>1,'msg'=>'视频已经采集过','mash'=>$data['mash']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }else{
                $identifier = $param['identifier'];
                if(empty($identifier)){
                    $identifier = $this->convert($param['mash']) . md5(time() . mt_rand(1, 1000000));
                }

                $videoData['video_url'] = $param['video_url'];
                $videoData['thumb'] = $param['thumb'];
                $videoData['preview'] = $param['preview'];
                $videoData['identifier'] = $identifier;
                $videoData['update_time'] = strtotime($param['publish_date']);
                $add_id = Video4KModel::insert($videoData);

                if(!$add_id){
                    $data['msg'] = '插入视频失败';
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }
                $data=['code'=>1,'msg'=>'插入视频成功','video_url'=>$data['video_url']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

        }

        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));

    }




    public function comic(){
        exit('禁止访问');
        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = '请传入视频信息';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_key_comic')){
                $data['msg'] = 'Api密钥为空/密钥错误';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['source_id'])){
                $data['msg'] = 'source_id不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['title'])){
                $data['msg'] = '标题不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['video_url'])){
                $data['msg'] = '播放地址不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $data['video_url'] = $param['video_url'];
            if(empty($param['thumb'])){
                $data['msg'] = '封面不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['preview'])){
                $data['msg'] = '预览图不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $panorama = !empty(trim($param['panorama']))?trim($param['panorama']):'';
            if(empty($param['publish_date'])){
                $data['msg'] = '发行时间不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $param['actor'] = !empty($param['actor'])?explode(',',trim($param['actor']))[0]:'佚名';
            $actor = Actor2Model::where('name','=',$param['actor'])->value('id');
            if (!$actor) {//如果演员不存在
                $actor = Actor2Model::insertGetId(['name' => $param['actor']]);
            }

            if(!empty($param['tags'])){
                $tags = $this->getTags2($param['tags']);
            }else{
                $tags = '';
            }
            $description = !empty(trim($param['description']))?trim($param['description']):'';


            $videoData = [
                'title'=>trim($param['title']),
                'source_id'=>$param['source_id'],
                'actor'=>$actor,
                'tags'=>$tags,
                'thumb'=>$param['thumb'],
                'preview'=>$param['preview'],
                'description'=>trim($description),
                'publish_date'=>$param['publish_date'],
                'insert_time'=>time()
            ];
            $vid = ComicModel::where('source_id','=',$param['source_id'])->value('id');
            if($vid){
/*                $up_id = ComicModel::where('id','=',$vid)->update($videoData);
                if($up_id === false){
                    $data['msg'] = '修改视频失败';
                    save_log($data, 'post_error');
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }*/

                $data=['code'=>1,'msg'=>'视频已经采集过','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }else{
                $identifier = $param['identifier'];
                if(empty($identifier)){
                    $identifier = $this->convert($param['video_url']) . md5(time() . mt_rand(1, 1000000));
                }
                $videoData['video_url'] = $param['video_url'];
                $videoData['identifier'] = $identifier;
                $videoData['update_time'] = strtotime($param['publish_date']);
                $add_id = ComicModel::insert($videoData);

                if(!$add_id){
                    $data['msg'] = '插入视频失败';
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }
                $data=['code'=>1,'msg'=>'插入视频成功','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

        }

        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));

    }


    protected function getTags($tags)
    {

        // 清理HTML实体
        $tags = str_replace('&nbsp;', '', $tags); // 直接移除&nbsp;
        $tags = explode(',',$tags);
        $result = [];
        foreach ($tags as $tag){
            $res = TagsModel::where('name','=',$tag)->value('id');
            if($res){
                $result[] = $res;
            }else{
                $result[] = TagsModel::insertGetId(['name' => $tag]);
            }
        }
        return implode(',',$result);
    }

    protected function getTags2($tags)
    {
        // 清理HTML实体
        $tags = str_replace('&nbsp;', '', $tags); // 直接移除&nbsp;
        
        $tags = explode(',',$tags);
        $result = [];
        foreach ($tags as $tag){
            $tag = trim($tag); // 确保每个标签都去除空格
            if(empty($tag)) continue; // 跳过空标签
            
            $res = TagsModel2::where('name','=',$tag)->value('id');
            if($res){
                $result[] = $res;
            }else{
                $result[] = TagsModel2::insertGetId(['name' => $tag]);
            }
        }
        return implode(',',$result);
    }

    protected function convert($str)
    {
        $pinyin = new Pinyin();
        $str = $pinyin->abbr($str);
        return $str;
    }

}