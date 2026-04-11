<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;
use think\Db;
use app\lib\exception\BaseException;
use app\index\model\Configs;

class FrontendImages extends Model
{
    protected $table  = 'frontend_images';
    const info_field  = 'id,key,thumb,thumb_es,thumb_en,thumb_zh,thumb_ru,thumb_ms,thumb_th,m_thumb,m_thumb_es,m_thumb_en,m_thumb_ru,m_thumb_ms,m_thumb_th,status,sort_order,created_at,updated_at';
    protected $hidden = ['thumb_es', 'thumb_en', 'thumb_zh', 'thumb_ru', 'thumb_ms', 'thumb_th','m_thumb_es', 'm_thumb_en', 'm_thumb_zh', 'm_thumb_ru', 'm_thumb_ms', 'm_thumb_th'];
    
    public function getThumbAttr($val, $data)
    {
        $thumb_url = Configs::get('thumb_url');
        $langMap = [
            'zh' => 'thumb',
            'en' => 'thumb_en',
            'ru' => 'thumb_ru',
            'ms' => 'thumb_ms',
            'th' => 'thumb_th',
            'es' => 'thumb_es',
        ];

        $result = [];
        foreach ($langMap as $lang => $field) {
            $url = null;
            if(!empty($data[$field])){
                $url = $thumb_url . '/' . ltrim($data[$field], '/');
                $result[$lang] = $url;
            }
        }
        return $result;
    }

    public function getMThumbAttr($val, $data)
    {
        $thumb_url = Configs::get('thumb_url');
        $langMap = [
            'zh' => 'm_thumb',
            'en' => 'm_thumb_en',
            'ru' => 'm_thumb_ru',
            'ms' => 'm_thumb_ms',
            'th' => 'm_thumb_th',
            'es' => 'm_thumb_es',
        ];

        $result = [];
        foreach ($langMap as $lang => $field) {
            $url = null;
            if(!empty($data[$field])){
                $url = $thumb_url . '/' . ltrim($data[$field], '/');
                $result[$lang] = $url;
            }
        }
        return $result;
    }

    public static function lists()
    {
        $where = [
            ['status', '=', 1]
        ];

        $query = self::field(self::info_field)
            ->where($where)
            ->order('sort_order', 'desc')
            ->order('id', 'asc')
            ->select();

        if ($query) {
            $data = $query->toArray();
            $results = $data;
        } else {
            $results = [];
        }

        return $results;
    }
}