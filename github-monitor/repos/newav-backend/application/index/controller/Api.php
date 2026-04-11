<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/21
 * Time: 15:45
 */

namespace app\index\controller;
use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Controller;
use app\index\model\Actor as ActorModel;
use app\index\model\Video as VideoModel;
use app\index\model\Articles as ArticlesModel;

use app\index\model\Trend as TrendModel;
use app\index\model\TrendDetail as TrendDetailModel;
use app\index\model\Links as LinksModel;
class Api extends Controller
{
    public function article(){

        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = '请传入影评信息';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_key_article')){
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

            if(empty($param['thumb'])){
                $data['msg'] = '封面不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

/*            if(empty($param['mash'])){
                $data['msg'] = '番号不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }*/

            $param['mash'] = !empty($param['mash'])?$param['mash']:'';

            if(empty($param['actor'])){
                $data['msg'] = '演员不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['publish_date'])){
                $data['msg'] = '发行时间不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['content'])){
                $data['msg'] = '内容不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            $aid = ActorModel::where('name','=',$param['actor'])->value('id') ?? 0;
            $vid = VideoModel::where('mash','=',$param['mash'])->value('id') ?? 0;
            $videoData = [
                'title'=>trim($param['title']),
                'source_id'=>$param['source_id'],
                'actor'=>$param['actor'],
                'aid'=>$aid,
                'mash'=>$param['mash'],
                'vid'=>$vid,
                'thumb'=>$param['thumb'],
                'content'=>$param['content'],
                'publish_date'=>$param['publish_date'],
            ];
            $ar_id = ArticlesModel::where('source_id','=',$param['source_id'])->value('id');
            if($ar_id){
                $data=['code'=>1,'msg'=>'影评已经采集过','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }else{
                $videoData['insert_time'] = time();
                $add_id = ArticlesModel::insert($videoData);

                if(!$add_id){
                    $data['msg'] = '插入影评失败';
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }
                $data=['code'=>1,'msg'=>'插入影评成功','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

        }

        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));

    }



    public function trend(){
        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            $data['code'] = 0;
            if(empty($param)){
                $data['msg'] = '请传入动态信息';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_key_trend')){
                $data['msg'] = 'Api密钥为空/密钥错误';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            if(empty($param['source_id'])){
                $data['msg'] = 'source_id不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['actor'])){
                $data['msg'] = '演员不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['created_at'])){
                $data['msg'] = '时间不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            $param['text'] = !empty($param['text'])?$param['text']:'';
            $param['media'] = !empty($param['media'])?$param['media']:'';

            $tid = TrendModel::where('name','=',$param['actor'])->value('id');
            if (!$tid) {//如果演员不存在
                $tid = TrendModel::insertGetId(['name' => $param['actor']]);
            }

            $is_actor = ActorModel::field('id,tid')->where('name','=',$param['actor'])->find();
            if($is_actor && $is_actor['tid'] == 0 ){
                ActorModel::where('id','=',$is_actor['id'])->setField('tid',$tid);
            }

            $detail = [
                'tid'=>$tid,
                'text'=>$param['text'],
                'create_at'=>$param['created_at'],
                'media'=>$param['media'],
                'source_id'=>$param['source_id']
            ];

            $td_id = TrendDetailModel::where('source_id','=',$param['source_id'])->value('id');
            if($td_id){
                $data=['code'=>1,'msg'=>'动态已经采集过','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }else{
                $add_id = TrendDetailModel::insert($detail);
                if(!$add_id){
                    $data['msg'] = '插入动态失败';
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }
                $data=['code'=>1,'msg'=>'插入动态成功','source_id'=>$param['source_id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
        }


        $data=['code'=>0,'msg'=>'请使用POST提交'];
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));
    }


    /**
     * Notes:
     *
     * DateTime: 2023/10/6 13:23更换域名
     */
    public function update_domain(){
        $domain = $this->request->param('base_domain');//获取所有参数，最全
        if(empty($domain)){
            $data=['code'=>0,'msg'=>'域名不能为空'];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        $this->update_links($domain);
        httpPost('https://madmin.9xyrp3kg4b86.com/api/update_domain',['base_domain'=>$domain]);
//        httpPost('http://ins_mh_admin.cn/api/update_domain',['base_domain'=>$domain]);
        $data = [
            // 'youma_domain'=>'https://www.'.$domain,
            // 'wuma_domain'=>'https://wuma.'.$domain,
            // 'dm_domain'=>'https://dm.'.$domain,
            // 'k4_domain'=>'https://4k.'.$domain
        ];

        try {
            // foreach ($data as $k=>$v){
            //     Configs::where('name','=',$k)->update(['value'=>$v,'update_at'=>time()]);
            // }

            $redis = new Redis();
            $keys = $redis->keys('config*');
            if($keys){
                $redis->del($keys);
            }
            $data=['code'=>1,'msg'=>'域名更换成功','base_domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            // 回滚事务
            $data=['code'=>0,'msg'=>'域名更换失败','base_domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }

    }



    /**
     * Notes:
     *
     * DateTime: 2023/10/6 13:23更换域名
     */
    private function update_links($domain){

        $data = [
            1=>'https://dm.'.$domain,
            2=>'https://4k.'.$domain,
            3=>'https://wuma.'.$domain,
            4=>'https://www.'.$domain,
        ];
        foreach ($data as $k=>$v){
            LinksModel::where('id','=',$k)->setField('url',$v);
        }
        $redis = new Redis();
        $keys = $redis->keys('links*');
        if($keys){
            $redis->del($keys);
        }
    }


    public function update_jm(){
        $domain = $this->request->param('base_domain');//获取所有参数，最全
        if(empty($domain)){
            $data=['code'=>0,'msg'=>'域名不能为空'];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        $res = LinksModel::where('id','=',5)->setField('url',$domain);
        if($res !== false){
            $data=['code'=>1,'msg'=>'域名更换成功','base_domain'=>$domain];
            $redis = new Redis();
            $keys = $redis->keys('links*');
            if($keys){
                $redis->del($keys);
            }
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }else{
            $data=['code'=>0,'msg'=>'域名更换失败','base_domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
    }




    public function update_ok(){

        $domain = $this->request->param('domain');
        if(empty($domain)){
            $data=['code'=>0,'msg'=>'域名不能为空'];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        try {
            LinksModel::where('id','=',6)->setField('url',$domain);
            $data=['code'=>1,'msg'=>'域名更换成功','domain'=>$domain];
            $redis = new Redis();
            $keys = $redis->keys('links*');
            if($keys){
                $redis->del($keys);
            }
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            // 回滚事务
            $data=['code'=>0,'msg'=>'域名更换失败','domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
    }


}