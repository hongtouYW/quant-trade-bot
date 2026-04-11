<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\facade\Request;
use think\Model;

class Articles extends Model
{

    protected $hidden = ['title_en','title_ru','content_en','content_ru'];

    // public function getThumbAttr($val){
    //     if(!empty($val)){
    //         $thumb_url = Configs::get('thumb_url');
    //         $val = $thumb_url.$val;
    //     }
    //     return $val;
    // }

    public function getActorAttr($val,$data){
        if(!empty($data['aid'])){
            $actor = Actors::field('id,name,name_en,name_ru')->where('id','=',$data['aid'])->find();
            if($actor){
                return $actor['name'];
            }
        }
        return $val;
    }

    public function getTitleAttr($val,$data){
        $lang = getLang();
        if ($lang == 'en' && !empty($data['title_en'])){
            return $data['title_en'];
        }

        if($lang == 'ru' && !empty($data['title_ru'])){
            return $data['title_ru'];
        }
        return $val;
    }

/*    public function getContentAttr($val,$data){

        $lang = getLang();
        $action = strtolower(Request::action());

        if($lang == 'en' && !empty($data['content_en'])){
            if($action == 'lists'){
                $val = preg_replace('~<p><img(.*?)></p>~s','',$data['content_en']);
            }else if($action == 'info'){
                $thumb_url = Configs::get('thumb_url');
                $val = preg_replace('/src="\//', 'src="'."$thumb_url".'/', $data['content_en']);
            }
            return $val;
        }

        if($lang == 'ru' && !empty($data['content_ru'])){
            if($action == 'lists'){
                $val = preg_replace('~<p><img(.*?)></p>~s','',$data['content_ru']);
            }else if($action == 'info'){
                $thumb_url = Configs::get('thumb_url');

                $val = preg_replace('/src="\//', 'src="'."$thumb_url".'/', $data['content_ru']);
            }
            return $val;
        }

        if($action == 'lists'){
            $val = preg_replace('~<p><img(.*?)></p>~s','',$val);
        }else if($action == 'info'){
            $thumb_url = Configs::get('thumb_url');
            $val = preg_replace('/src="\//', 'src="'."$thumb_url".'/', $val);
        }
        return $val;

    }*/


    public function getContentAttr($val, $data) {
        $lang = getLang();
        $action = strtolower(Request::action());

        if ($lang == 'en' && !empty($data['content_en'])) {
            if ($action == 'lists') {
                $val = preg_replace('~<p><img(.*?)></p>~s', '', $data['content_en']);
            } else if ($action == 'info') {
                $thumb_url = Configs::get('thumb_url');
                $val = preg_replace_callback('/src="([^"]*)"/', function ($matches) use ($thumb_url) {
                    $originalUrl = $matches[1];
                    if (strpos($originalUrl, '/') === 0) {
                        $originalUrl = $thumb_url . $originalUrl;
                    }
                    return 'src="' . self::jianquanImg($originalUrl) . '"';
                }, $data['content_en']);
            }
            return $val;
        }

        if ($lang == 'ru' && !empty($data['content_ru'])) {
            if ($action == 'lists') {
                $val = preg_replace('~<p><img(.*?)></p>~s', '', $data['content_ru']);
            } else if ($action == 'info') {
                $thumb_url = Configs::get('thumb_url');
                $val = preg_replace_callback('/src="([^"]*)"/', function ($matches) use ($thumb_url) {
                    $originalUrl = $matches[1];
                    if (strpos($originalUrl, '/') === 0) {
                        $originalUrl = $thumb_url . $originalUrl;
                    }
                    return 'src="' . self::jianquanImg($originalUrl) . '"';
                }, $data['content_ru']);
            }
            return $val;
        }

        if ($action == 'lists') {
            $val = preg_replace('~<p><img(.*?)></p>~s', '', $val);
        } else if ($action == 'info') {
            $thumb_url = Configs::get('thumb_url');
            $val = preg_replace_callback('/src="([^"]*)"/', function ($matches) use ($thumb_url) {
                $originalUrl = $matches[1];
                if (strpos($originalUrl, '/') === 0) {
                    $originalUrl = $thumb_url . $originalUrl;
                }
                return 'src="' . self::jianquanImg($originalUrl) . '"';
            }, $val);
        }
        return $val;
    }



    private static function jianquanImg($url){
        $parse = parse_url($url);
        $expiryTime = 300; // 有效期（秒）
        $secretKey = 'Z7tSXMjByKCFzF5tyApk8cFpjQXD8zWd';
        $wstime = time() + $expiryTime; // 当前时间戳 + 有效期
        $uri = $parse['path']; // 资源路径
        $group = $secretKey . $uri . $wstime; // 生成鉴权组合：密钥 + 路径 + 时间戳
        $wsSecret = md5($group); // 使用 MD5 加密生成签名
        return $url."?wsSecret=" . $wsSecret . "&wsTime=" . $wstime;
    }


    public static function lists($page,$limit){

        $redis = new Redis();
        $lang = getLang();
        $redis_key = 'article_list_'.$page.'_'.$limit.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $results = self::field('id,title,title_en,title_ru,mash,aid,vid,actor,thumb,publish_date,content,content_en,content_ru')
                ->where('status','=',1)
                ->order('id desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 240);;
        }
        return $results;

    }

    public static function info($id){
        $field = 'id,title,mash,aid,vid,actor,thumb,publish_date,content';
        $redis_key = 'article_info_'.$id;
        $redis = new Redis();
        $info = $redis->get($redis_key);
        if(!$info){
            $info = self::field($field)->where('status','=',1)->where('id','=',$id)->find();
            if($info) $redis->set($redis_key, $info, 240); //1天
        }
        return $info;
    }
}