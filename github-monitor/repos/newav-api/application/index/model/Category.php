<?php

namespace app\index\model;

use think\cache\driver\Redis;
use think\Model;

class Category extends Model
{
    protected $table = 'category';
    const list_field = 'id,name,name_en,name_ru,name_ms,name_th,name_es';
    protected $hidden = ['name_en','name_ru','name_ms','name_th','name_es'];

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

    public static function lists($page,$limit){
        $redis     = new Redis();
        $lang      = getLang();
        $redis_key = 'category_list_'.$page.'_'.$limit.'_'.$lang;
        $results   = $redis->get($redis_key);
        if(!$results){
            $where      = "is_show = 1";
            $order      = 'sort asc';
            $categories = self::field(self::list_field)
                ->where($where)
                ->order($order)
                ->paginate([
                    'list_rows' => $limit,
                    'page'     => $page,
                ])->toArray();

            if (!empty($categories['data'])) {
                foreach ($categories['data'] as &$category) {
                    $category['tags'] = Tags::getTagsByCategory($category['id']);
                }
                $results = $categories;
                $redis->set($redis_key, $results, 86400);
            }
        }
        return $results;
    }
}