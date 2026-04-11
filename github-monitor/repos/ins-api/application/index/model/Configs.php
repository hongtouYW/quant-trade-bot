<?php

namespace app\index\model;
use think\cache\driver\Redis;
use think\Model;
use app\lib\exception\BaseException;

class Configs extends Model
{

    const noReturn = ['video_url','thumb_url','deduction','p_list','mmadv_url','is_force','youma_domain','wuma_domain','wuma_video_url'
        ,'dm_thumb_url','dm_video_url','dm_domain','4k_video_url','4k_thumb_url','k4_domain','api_url','cpa_url_reg','cpa_url_charge',
    'notice_en','notice_ru','video_adv_en','video_adv_ru'];

    public static function lists()
    {
        //Config save to redis
        $lang = getLang();
        $redis_key = 'config_list_'.$lang;
        $redis = new Redis();
        $arr = $redis->get($redis_key);
        if(!$arr)
        {
            $arr = [];
            $data = self::field('name,value')->select()->toArray();
            foreach($data as $k => $v)
            {
                if($lang == 'en'){
                    if($v['name'] == 'notice'){
                       $data[$k]['value'] = self::get('notice_en');
                    }
                    if($v['name'] == 'video_adv'){
                        $data[$k]['value'] = self::get('video_adv_en');
                    }
                }
                if($lang == 'ru'){
                    if($v['name'] == 'notice'){
                        $data[$k]['value'] = self::get('notice_ru');
                    }
                    if($v['name'] == 'video_adv'){
                        $data[$k]['value'] = self::get('video_adv_ru');
                    }
                }

                if(!in_array($v['name'],self::noReturn)){
                    $arr[$data[$k]["name"]] = $data[$k]["value"];
                }

            }
            //save cache
            if($arr) $redis->set($redis_key, $arr,86400);
        }
        return $arr;
    }


    public static function get($name)
    {

        //Config save to redis
        $redis = new Redis();
        $redis_key = 'config_'.$name;
        $results = $redis->get($redis_key);

        if(!$results)
        {
            $results = self::where("name",'=',$name)->value('value');
            
/*            if($name == 'thumb_url'){
                $results = 'https://mig.zzbabylon.com';
            }
            if($name == 'video_url'){
                $results = 'https://ut.lnh7.com';
            }
            if($name == 'dm_thumb_url'){
                $results = 'https://mig.zzbabylon.com/hanime1';
            }
            if($name == 'dm_video_url'){
                $results = 'https://ut.lnh7.com/hanime1';
            }
            if($name == '4k_thumb_url'){
                $results = 'https://mig.zzbabylon.com/4k';
            }
            if($name == '4k_video_url'){
                $results = 'https://ut.lnh7.com/4k';
            }*/
            if(!isset($results)) throw new BaseException(1003);
            //save cache
            $redis->set($redis_key, $results,86400);
        }
        return $results;
    }

}