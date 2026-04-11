<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2023/4/21
 * Time: 15:45
 */

namespace app\index\controller;

use app\index\model\Chapter;
use app\index\model\Configs;
use think\cache\driver\Redis;
use think\Controller;
use app\index\model\Links as LinksModel;
use app\index\model\Comic as ComicModel;


class Api extends Controller
{


    public function get_ids()
    {
        $where['status'] = 1;
        $where['mhstatus'] = 0;
        $where['is_caiji'] = 1;
        $data = ComicModel::field('cjid as book_id,last_chapter as section_id')->where($where)->order('id desc')->limit(20)->select();
        echo json_encode($data);die;
    }



    public function comic_update(){
        exit('漫画更新接口已关闭');
        $param = $this->request->param();//获取所有参数，最全
        $data['code'] = 0;
        if(empty($param)){
            $data['msg'] = '请传入漫画信息';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        save_log($param,'comic_insert');

        if (empty($param['title'])) {
            $data['msg'] = 'title参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        if (!in_array($param['mhstatus'], [0, 1])) {
            $data['msg'] = 'mhstatus参数只能0或者1';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        # 最新章节
        if (!isset($param['last_chapter_title'])) {
            $data['msg'] = 'last_chapter_title参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        # 书籍简介
/*        if (!isset($param['desc'])) {
            $data['msg'] = '介绍不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }*/
        $param['desc'] = !empty($param['desc']) ? $param['desc'] : '';
        $param['auther'] = !empty($param['auther']) ? $param['auther'] : '佚名';

        # 采集标识,书籍在该站唯一的书籍数据id
        if (!isset($param['cjid'])) {
            $data['msg'] = 'cjid参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }

        $param['cjname'] = 'toptoon';  # 采集渠道
        # 添加时间或者更新时间
        $param['create_time'] = $param['update_time'] = time();
        $info = ComicModel::field('id,last_chapter')->where('cjid','=',$param['cjid'])->where('cjname','=',$param['cjname'])->find();
        if($info){
            if ((int)$info['last_chapter'] != (int)$param['last_chapter_id']) {
                $save=[
                    'mhstatus'=>$param['mhstatus'],
                    'cjstatus'=>0,
                ];
                ComicModel::where('id','=',$info['id'])->update($save);
                $msg = '书籍: ' . $param['title'] . ', 有更新';
                $data=['code'=>1,'msg'=>$msg,'manhua_id'=>$info['id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            } else {
                ComicModel::where('id','=',$info['id'])->setField('mhstatus',$param['mhstatus']);
                $msg = '书籍: ' . $param['title'] . ', 无更新！！';
                $data=['code'=>0,'msg'=>$msg,'manhua_id'=>$info['id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
        }else{
            if (empty($param['image'])) {
                $data['msg'] = 'image参数不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if (empty($param['cover'])) {
                $data['msg'] = 'cover参数不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            $param['last_chapter'] = $param['last_chapter_id'];
            $param['status'] = 0;
            $param['isfree'] = 1;
            $param['vipcanread'] = 1;
            $param['is_caiji'] = 1;
            unset($param['last_chapter_id']);
            $id = ComicModel::insertGetId($param);
            $data=['code'=>1,'msg'=>'漫画插入成功','manhua_id'=>$id];
            save_log($data, 'add_success');
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
    }


    public function chapter_update(){
        exit('章节更新接口已关闭');
        $param = $this->request->param();//获取所有参数，最全
        $data['code'] = 0;
        if(empty($param)){
            $data['msg'] = '请传入章节信息';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        save_log($param,'comic_chapter');

        if (empty($param['title'])) {
            $data['msg'] = 'title参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        # 书籍返回的主键id
        if (empty($param['manhua_id'])) {
            $data['msg'] = 'manhua_id参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        # 采集标识,采集站章节对应的唯一id
        if (empty($param['cjid'])) {
            $data['msg'] = 'cjid参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        # 采集标识,采集站章节对应的唯一id
        if (empty($param['sort'])) {
            $data['msg'] = 'sort参数不能为空';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        $param['create_time'] = $param['update_time'] = time();# 添加时间或者更新时间
        if($param['sort'] < 7)
        {
            $param['isvip'] = 0; # 性别:0=免费,1=VIP
            $param['score'] = 0; # 售价
        }else{
            $param['isvip'] = 1; # 性别:0=免费,1=VIP
            $param['score'] = 1; # 售价
        }
        $param['switch'] = 1; # 上架状态:0=下架,1=正常'
        $param['type'] = 1; # 类型:2=小说,1=漫画
        $param['cjname'] = 'toptoon'; # 采集渠道

        $info = Chapter::where('cjname','=',$param['cjname'])->where('cjid','=',$param['cjid'])->find();

        if($info){
            $msg = '章节: ' . $param['title'] . ', 无更新！！';
            $data=['code'=>0,'msg'=>$msg,'chapter_id'=>$info['id']];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }else{

            if(empty($param['image'])){
                $data['msg'] = 'image参数不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            if(empty($param['imagelist'])){
                $data['msg'] = 'imagelist参数不能为空';
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }

            $id = Chapter::insertGetId($param);
            $msg = '章节: ' . $param['title'] . '更新成功; ';
            if ($id) {
                $up_comic = [
                    'last_chapter' => $param['cjid'],
                    'last_chapter_title' => $param['title'],
                    'update_time' => $param['create_time'],
                    'cjstatus' => 1,
                    'status' => 1
                ];
                ComicModel::where('id','=',$param['manhua_id'])->update($up_comic);
                $data=['code'=>1,'msg'=>$msg,'chapter_id'=>$id];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            } else {
                $data=['code'=>0,'msg'=>'章节更新失败','chapter_id'=>$info['id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
        }
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
        $data = [
            'YOUMA_DOMAIN'=>'https://www.'.$domain,
            'WUMA_DOMAIN'=>'https://wuma.'.$domain,
            'DM_DOMAIN'=>'https://dm.'.$domain,
            'K4_DOMAIN'=>'https://4k.'.$domain
        ];

        try {
            foreach ($data as $k=>$v){
                Configs::where('name','=',$k)->update(['value'=>$v,'update_time'=>date('Y-m-d H:i:s')]);
            }

            $redis = new Redis();
            $keys = $redis->keys('config*');
            if($keys){
                $redis->del($keys);
            }

            $linkData = [
                1=>'https://www.'.$domain,
                2=>'https://wuma.'.$domain,
                3=>'https://dm.'.$domain,
                4=>'https://4k.'.$domain,
            ];
            foreach ($linkData as $k=>$v){
                LinksModel::where('id','=',$k)->setField('url',$v);
            }

            $data=['code'=>1,'msg'=>'域名更换成功','base_domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            // 回滚事务
            $data=['code'=>0,'msg'=>'域名更换失败','base_domain'=>$domain];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }

    }


    public function update_jm(){

        $domain = $this->request->param('base_domain');//获取所有参数，最全
        if(empty($domain)){
            $data=['code'=>0,'msg'=>'域名不能为空'];
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        $domain = 'https://www.'.$domain;
        httpPost('https://admin.uxb89.com/api/update_jm',['base_domain'=>$domain]);
//        httpPost('http://ins1.cn/api/update_jm',['base_domain'=>$domain]);
        try {
            LinksModel::where('id','=',5)->setField('url',$domain);
            $data=['code'=>1,'msg'=>'域名更换成功','base_domain'=>$domain];
            $redis = new Redis();
            $keys = $redis->keys('links*');
            if($keys){
                $redis->del($keys);
            }
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            // 回滚事务
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