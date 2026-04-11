<?php

namespace app\traits;

use think\facade\Config as AppConfig;

trait HasProjectVisibility
{
    // 项目可见性枚举
    protected static $VISIBILITY_ALL = 1;       // 全部可见
    protected static $VISIBILITY_OVERSEAS = 2;  // 仅海外可见 (GB)
    protected static $VISIBILITY_DOMESTIC = 3;  // 仅国内可见 (CN)

    /**
     * 向查询对象添加项目可见性过滤条件
     * 根据当前项目类型（CN / GB）限制可见内容
     *
     * @param \think\db\Query $query
     * @param string $alias 可选表别名（默认 'm'）
     * @param string $columnName 可选字段名（默认 'project_visibility'）
     * @return void
     */
    public static function applyProjectVisibility(
        \think\db\Query $query,
        string $alias = 'm',
        string $columnName = 'project_visibility'
    ): void {
        // 读取当前项目类型（CN=国内，GB=海外）
        $projectType = AppConfig::get('app.current_project_type');

        // 基础可见性
        $visibility = [self::$VISIBILITY_ALL];

        // 追加项目类型可见性
        if ($projectType === 'CN') {
            $visibility[] = self::$VISIBILITY_DOMESTIC;
        } elseif ($projectType === 'GB') {
            $visibility[] = self::$VISIBILITY_OVERSEAS;
        }

        // 拼接字段名（自动判断是否带别名）
        $column = $alias ? "{$alias}.{$columnName}" : $columnName;

        // 添加过滤条件
        $query->whereIn($column, $visibility);
    }
}
