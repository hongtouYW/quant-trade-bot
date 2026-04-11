<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/24
 * Time: 14:02
 */

namespace app\index\controller;

use Overtrue\Pinyin\Pinyin;
use think\Controller;
use app\index\model\Video as VideoModel;
use app\index\model\Actor as ActorModel;
use app\index\model\Publisher as PublisherModel;
use app\index\model\Tags as TagsModel;
class Upload extends Controller
{

    public function video(){

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
                'zimu'=>$param['zimu']
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
                $videoData['identifier'] = $identifier;
                $videoData['insert_time'] = $videoData['update_time'] = strtotime($param['publish_date']);
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

    protected function getTags($tags)
    {
        $id = TagsModel::where('name','in',$tags)->column('id');
        return implode(',',$id);
    }

    protected function convert($str)
    {
        $pinyin = new Pinyin();
        $str = $pinyin->abbr($str);
        return $str;
    }

}