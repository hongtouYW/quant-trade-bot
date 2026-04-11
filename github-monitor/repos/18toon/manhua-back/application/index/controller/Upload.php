<?php
/**
 * Created by PhpStorm.
 * User: Joker
 * Date: 2022/8/24
 * Time: 14:02
 */

namespace app\index\controller;
use app\index\model\Chapter;
use think\Controller;
use app\index\model\Comic as ComicModel;
use app\index\model\Tags as TagsModel;
class Upload extends Controller
{
    public function jmtt_comic(){

        if (\request()->isPost()) {
            $param = $this->request->param();//获取所有参数，最全
            save_log($param, "jmtt_param");
            $data['code'] = 0;
            if (empty($param)) {
                $data['msg'] = '请传入漫画信息';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            if (!isset($param['api_key']) || $param['api_key'] != config('site.api_jmtt_key')) {
                $data['msg'] = 'Api密钥为空/密钥错误';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }


            //{'title': '情定大阪', 'author': 'ⓒ kelly_o', 'desc': '和岛国妹子激情一夜,彻底抚平我被甩的创伤！', 'progress_status': 0, 'book_id': '7354', 'image': '/toptoon/tomic/logo/7354/b.jpg', 'cover': '/toptoon/tomic/logo/7354/a.jpg', 'keyword': '剧情,浪漫爱情', 'last_chapter_id': '201560'}
            if (empty($param['title'])) {
                $data['msg'] = 'title参数不能为空';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            if (!in_array($param['mhstatus'], [0, 1])) {
                $data['msg'] = 'mhstatus参数只能0或者1';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            # 最新章节
            if (!isset($param['last_chapter_title'])) {
                $data['msg'] = 'last_chapter_title参数不能为空';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            # 书籍简介
            if (!isset($param['desc'])) {
                $data['msg'] = '介绍不能为空';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            # 书籍作者
            if (!isset($param['author'])) {
                $data['msg'] = '作者不能为空';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            # 采集标识,书籍在该站唯一的书籍数据id
            if (!isset($param['cjid'])) {
                $data['msg'] = 'cjid参数不能为空';
                exit(json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            if(!empty($param['tags'])){
                $tags = $this->getTags($param['tags']);
            }else{
                $tags = '';
            }



            $cjname = 'jmtt';  # 采集渠道
            $time = time();

            $is_title = ComicModel::where('title','=',$param['title'])->where('cjname','=',$cjname)->value('id');
            if($is_title){
                $data=['code'=>0,'msg'=> '书籍: ' . $param['title'] . ', 已存在','manhua_id'=>$is_title];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
            # 添加时间或者更新时间
            $info = ComicModel::field('id,last_chapter')->where('cjid','=',$param['cjid'])->where('cjname','=',$cjname)->find();
            if($info){
                $save = [
                    'title' => $param['title'],
                    'auther' => $param['author'],
                    'desc' => $param['desc'],
                    'mhstatus'=>$param['mhstatus'],
                    'image' => $param['image'],
                    'cover' => $param['cover'],
                    'keyword' => $param['keyword'],
                    'tags' => $tags,
                ];
                ComicModel::where('id','=',$info['id'])->update($save);
                if((int)$info['last_chapter'] != (int)$param['last_chapter_id']){
                    $msg = '书籍: ' . $param['title'] . ', 有更新';
                    $data=['code'=>1,'msg'=>$msg,'manhua_id'=>$info['id']];
                    exit(json_encode($data,JSON_UNESCAPED_UNICODE));
                }else{
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
                $add_data = [
                    'title' => $param['title'],
                    'create_time' =>$time,
                    'update_time' => $time,
                    'auther' => $param['author'],
                    'desc' => $param['desc'],
                    'mhstatus'=>$param['mhstatus'],
                    'cjid'=> $param['cjid'],
                    'image' => $param['image'],
                    'cover' => $param['cover'],
                    'keyword' => $param['keyword'],
/*                    'last_chapter' => $param['last_chapter_id'],
                    'last_chapter_title' => $param['last_chapter_title'],*/
                    'tags' => $tags,
                    'cjname' => $cjname,
                    'status'=>0,
                    'vipcanread'=>1,
                    'is_caiji'=>1,

                ];
                $id = ComicModel::insertGetId($add_data);
                $data=['code'=>1,'msg'=>'漫画插入成功','manhua_id'=>$id];
                save_log($data, 'add_success');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
        }
    }

    public function jmtt_chapter(){
        $param = $this->request->param();//获取所有参数，最全
        $data['code'] = 0;
        if(empty($param)){
            $data['msg'] = '请传入章节信息';
            exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
        save_log($param,'jmtt_chapter');

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
        $param['cjname'] = 'jmtt'; # 采集渠道

        $info = Chapter::where('cjname','=',$param['cjname'])->where('cjid','=',$param['cjid'])->find();

        if($info){
            $msg = '章节: ' . $param['title'] . ', 更新章节图片！！';
            $up_chpater = [
                'image'=>$param['image'],
                'imagelist'=>$param['imagelist'],
                'update_time'=>$param['update_time'],
            ];
            Chapter::where('id','=',$info['id'])->update($up_chpater);

            $up_comic = [
                'last_chapter' => $param['cjid'],
                'last_chapter_title' => $param['title'],
                'update_time' => $param['update_time'],
                'status'=>1,
            ];
            ComicModel::where('id','=',$param['manhua_id'])->update($up_comic);
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
            if ($id) {
                $msg = '章节: ' . $param['title'] . '新增成功; ';
                $up_comic = [
                    'last_chapter' => $param['cjid'],
                    'last_chapter_title' => $param['title'],
                    'update_time' => $param['update_time'],
                    'status'=>1,
                ];
                ComicModel::where('id','=',$param['manhua_id'])->update($up_comic);
                $data=['code'=>1,'msg'=>$msg,'chapter_id'=>$id];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            } else {
                $data=['code'=>0,'msg'=>'章节新增失败','chapter_id'=>$info['id']];
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
            }
        }
    }


    protected function getTags($tags)
    {

        $tags = explode(',',$tags);
        $result = [];
        foreach ($tags as $tag){
            $res = TagsModel::where('name','=',$tag)->value('id');
            if($res){
                $result[] = $res;
                TagsModel::where('id','=',$res)->setInc('comic_count');
            }else{
                $result[] = TagsModel::insertGetId(['name' => $tag,'comic_count'=>1]);
            }
        }
        return implode(',',$result);
    }
}