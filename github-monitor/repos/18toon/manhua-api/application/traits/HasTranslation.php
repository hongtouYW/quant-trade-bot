<?php

namespace app\traits;

use think\Db;

trait HasTranslation
{
    /**
     * 给查询对象添加语言联表和字段选择逻辑
     *
     * @param \think\db\Query|\think\Model $query
     * @param string $alias 数据主表别名，如 'm'、'a'、'c'
     * @param string $lang 当前语言
     * @param array $fields 要处理翻译的字段，如 ['title', 'desc']
     * @param string $transTable 翻译表名，如 'manhua_trans'
     * @param string $transIdField 翻译表关联主表的字段，如 'manhua_id'
     * @return \think\db\Query|\think\Model
     */
    public static function withTranslation($query, $alias, $lang, array $fields, $transTable, $transIdField, $connection = null)
    {
        $dbConfig = self::getDbConfig($connection);

        $database = $dbConfig['database'] ?? '';
        $prefix = $dbConfig['prefix'] ?? '';

        // 判断是否以prefix开头
        if ($prefix && strpos($transTable, $prefix) === 0) {
            // 带前缀，直接用表名，框架会自动处理
            $tableForJoin = $transTable;
        } else {
            // 不带前缀，拼全名，防止自动加前缀
            $tableForJoin = "`{$database}`.`{$transTable}`";
        }

        if ($lang !== 'zh') {
            foreach ($fields as $field) {
                // $query->field("IFNULL(t.`$field`, $alias.`$field`) as `$field`");
                $query->field("IF(t.`$field` IS NULL OR t.`$field` = '', $alias.`$field`, t.`$field`) as `$field`");
            }

            $query->leftJoin("$tableForJoin t", "$alias.id = t.$transIdField AND t.lang_code = \"$lang\"");
        } else {
            if (!empty($fields)) {
                $firstField = $fields[0];

                foreach ($fields as $field) {
                    $query->field("$alias.`$field`");
                }

                $query->whereRaw("$alias.`$firstField` IS NOT NULL AND $alias.`$firstField` != ''");
            }
        }

        return $query;
    }

    public static function getDbConfig($connection = null)
    {
        if ($connection) {
            // 子连接配置
            return Db::connect($connection)->getConfig();
        }

        // 默认连接配置，去掉子连接数组（如 mh_db）
        $config = Db::getConfig();
        foreach ($config as $key => $val) {
            if (is_array($val)) {
                unset($config[$key]);
            }
        }
        return $config;
    }
}
