<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;

class Tags extends Model
{
    protected $table  = 'tag';
    const list_field  = 'id,name,name_en,name_ru,name_ms,name_th,name_es,image,video_count';
    protected $hidden = ['name_zh','name_en','name_ru','name_ms','name_th','name_es'];

    public function getNameAttr($val,$data)
    {
        $lang = strtolower(getLang());
        $langMap = [
            'zh' => 'name',
            'en' => 'name_en',
            'ru' => 'name_ru',
            'ms' => 'name_ms',
            'th' => 'name_th',
            'es' => 'name_es',
        ];

        if (isset($langMap[$lang])) {
            $field = $langMap[$lang];
            if (!empty($data[$field])) {
                return $data[$field];
            }
        }
        return $val;
    }

    public function getImageAttr($val){
        if(!empty($val)){
            $thumb_url = Configs::get('thumb_url');
            $val = $thumb_url.$val;
        }
        return $val;
    }

    public static function lists($page,$limit,$keyword,$top){

        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'video_tags_'.$page.'_'.$limit.'_'.$keyword.'_'.$lang.'_'.$top;
        $results   = $redis->get($redis_key);
        if(!$results){
            $where = "is_show = 1";
            if($keyword){
                $keyword = trim($keyword);
                $where .= " and (instr(name, '".$keyword."') or instr(name_en, '".$keyword."') or instr(name_ru, '".$keyword."'))";
            }
            if ($top !== null && $top !== '') {
                $where .= " AND is_top = " . intval($top);
            }

            $order = 'sort asc';
            $results = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();
            if ($results['data']) $redis->set($redis_key, $results, 86400);;
        }
        return $results;
    }

    public static function getTagsByCategory($categoryId) {
        $lang  = getLang();
        // $field = 'id,name,name_en,name_ru';

        $tags = self::field(self::list_field)
            ->where('category', '=', $categoryId)
            ->where('is_show', '=', 1)
            ->order('sort desc, id asc')
            ->select()
            ->toArray();

        // Localize tag names
        foreach ($tags as &$tag) {
            if ($lang == 'en' && !empty($tag['name_en'])) {
                $tag['name'] = $tag['name_en'];
            } else if ($lang == 'ru' && !empty($tag['name_ru'])) {
                $tag['name'] = $tag['name_ru'];
            }
            unset($tag['name_en'], $tag['name_ru']);
        }

        return $tags;
    }

    public static function homeList($page, $limit)
    {
        $redis          = new Redis();
        $lang           = getLang();
        $delayHours     = Configs::get('video_delay_hours');
        $redis_key      = 'video_tags_home_list_' . $page . '_' . $limit . '_' . $lang;
        $results        = $redis->get($redis_key);
        $delayTimestamp = time() - ($delayHours * 3600); // only get the video insert time more than $delayHours

        if ($results) {
            // $results = json_decode($results, true);
            // $results = json_decode(gzdecode($results), true);
        }else{
            $results = Tags::where('is_menu', 1)
                        ->field(self::list_field)
                        ->order('sort', 'asc')
                        // ->order('sort asc, id asc')
                        ->paginate([
                            'list_rows' => $limit,
                            'page'      => $page,
                        ])->toArray();

            foreach ($results['data'] as &$tag) {
                $video_ids = Db::name('video')
                    ->where('status', 1)
                    ->where('insert_time', '<=', $delayTimestamp)
                    ->whereRaw("FIND_IN_SET({$tag['id']}, tags)")
                    ->orderRaw('rand()') 
                    // ->order('update_time', 'desc')
                    ->limit(10)
                    ->column('id');

                $tag['video_list'] = [];
                foreach ($video_ids as $vid) {
                    try {
                        $tag['video_list'][] = Video::info($vid);
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }
            // $redis->setex($redis_key, 3600, gzencode(json_encode($results, JSON_UNESCAPED_UNICODE)));

            // $redis->setex($redis_key, 3600, json_encode($results, JSON_UNESCAPED_UNICODE));

            // $redis->set($redis_key, $results, 86400); // cache 1 day
        }
        return $results;
    }

    // public static function homeList($page, $limit)
    // {
    //     $redis          = new Redis();
    //     $lang           = getLang();
    //     $delayHours     = Configs::get('video_delay_hours');
    //     $redis_key      = 'video_tags_home_list_' . $page . '_' . $limit . '_' . $lang;
    //     $delayTimestamp = time() - ($delayHours * 3600); // only get the video insert time more than $delayHours

    //     // --- Step 1: Try fetch from Redis ---
    //     $cached = $redis->get($redis_key);
    //     if ($cached) {
    //         $results = json_decode($cached, true);
    //         if (is_array($results)) {
    //             return $results;
    //         }
    //     }

    //     // --- Step 2: Build from DB (same logic, unchanged) ---
    //     $results = Tags::where('is_menu', 1)
    //         ->field(self::list_field)
    //         ->order('id', 'asc')
    //         ->paginate([
    //             'list_rows' => $limit,
    //             'page'      => $page,
    //         ])->toArray();

    //     foreach ($results['data'] as &$tag) {
    //         $video_ids = Db::name('video')
    //             ->where('status', 1)
    //             ->where('insert_time', '<=', $delayTimestamp)
    //             ->whereRaw("FIND_IN_SET({$tag['id']}, tags)")
    //             ->order('update_time', 'desc')
    //             ->limit(15)
    //             ->column('id');

    //         $tag['video_list'] = [];
    //         foreach ($video_ids as $vid) {
    //             try {
    //                 $tag['video_list'][] = Video::info($vid);
    //             } catch (\Throwable $e) {
    //                 continue;
    //             }
    //         }
    //     }

    //     // --- Step 3: Save to Redis efficiently ---
    //     // // Compress only if data > 500KB to avoid slowdown
    //     // $encoded = json_encode($results, JSON_UNESCAPED_UNICODE);
    //     // if (strlen($encoded) > 500000) {
    //     //     // use lightweight compression
    //     //     $encoded = gzcompress($encoded, 4);
    //     //     $redis->setex($redis_key . '_gz', 86400, $encoded);
    //     // } else {
    //     //     $redis->setex($redis_key, 86400, $encoded);
    //     // }

    //     return $results;
    // }

}