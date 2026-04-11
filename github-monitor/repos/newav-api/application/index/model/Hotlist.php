<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;

class Hotlist extends Model
{
    protected $table = 'hotlist_category';

    const list_field   = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,sub_title,sub_title_zh,sub_title_en,sub_title_ru,sub_title_ms,sub_title_th,sub_title_es,image';
    const info_field   = 'id,title,title_zh,title_en,title_ru,title_ms,title_th,title_es,sub_title,sub_title_zh,sub_title_en,sub_title_ru,sub_title_ms,sub_title_th,sub_title_es,image';
    protected $hidden  = ['title_zh','title_en','title_ru','title_ms','title_th','title_es','sub_title_zh','sub_title_en','sub_title_ru','sub_title_ms','sub_title_th','sub_title_es'];
    protected $append  = ['total_video'];

    public function getTotalVideoAttr($value, $data)
    {
        return Db::name('hotlist_category_details')
            ->where('hotlist_category_id', $data['id'])
            ->count();
    }
    
    public function getVideosAttr($value, $data)
    {
        $video_ids = Db::name('hotlist_category_details')
            ->where('hotlist_category_id', $data['id'])
            ->column('video_id');

        $list = [];
        foreach ($video_ids as $vid) {
            try {
                $info = Video::info($vid);
                $list[] = $info;
            } catch (\Exception $e) {
                // Optionally skip videos with error or log them
                continue;
            }
        }

        return $list;
    }
    
    public function getImageAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public function getTitleAttr($val,$data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'title',
            'en' => 'title_en',
            'ru' => 'title_ru',
            'ms' => 'title_ms',
            'th' => 'title_th',
            'es' => 'title_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getSubTitleAttr($val, $data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'sub_title',
            'en' => 'sub_title_en',
            'ru' => 'sub_title_ru',
            'ms' => 'sub_title_ms',
            'th' => 'sub_title_th',
            'es' => 'sub_title_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public static function getHid(){
        $hid   = getInput('hid');
        $hotlist = self::field(self::info_field)->where('is_show','=',1)->where('id','=',$hid)->find();
        if(!$hotlist){
            throw new BaseException(5001);
        }
        return $hotlist['id'];
    }
    
    public static function lists($page,$limit,$keyword)
    {
        $redis = new Redis();
        $lang  = getLang();
        $redis_key = 'hotlist_'.$page.'_'.$limit.'_'.$keyword.'_'.$lang;
        $results = $redis->get($redis_key);
        if(!$results){
            $where = "";
            if($keyword){
                $keyword = preg_replace("/([a-zA-Z]+)\s*(\d+)/", "$1-$2", $keyword);
                $where   = "(instr(title, '".$keyword."') or instr(title_en, '".$keyword."') or instr(title_ru, '".$keyword."') )";
            }
            $results = self::field(self::list_field)
                ->where($where)
                ->where("is_show = 1")
                ->order('sort', 'asc')
                ->paginate([
                    'list_rows' => $limit,
                    'page'      => $page,
                ])->toArray();

            // Inject total_video for each hotlist category
            foreach ($results['data'] as &$hotlist) {
                $hotlist['total_video'] = Db::name('hotlist_category_details')
                    ->where('hotlist_category_id', $hotlist['id'])
                    ->count();
            }
            if ($results['data']) $redis->set($redis_key, $results, 3600);;
        }
        return $results;
    }

    public static function info($hid)
    {
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'hotlist_info_' . $hid.'_'.$lang;
        $results   = $redis->get($redis_key);

        if (!$results) {
            $results = self::field(self::info_field)
            ->where('id','=', $hid)->where('is_show','=',1)->find();
            if ($results) $redis->set($redis_key, $results, 3600); //1小时
        }
        if (!$results) throw new BaseException(5001);
        $hotlistModel = new self($results);
        $results['videos']      = $hotlistModel->getVideosAttr(null, $results);
        return $results;
    }
}