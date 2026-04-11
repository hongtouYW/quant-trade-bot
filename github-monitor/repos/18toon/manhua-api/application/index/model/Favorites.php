<?php

namespace app\index\model;

use think\Model;

class Favorites extends Model
{
    public function getFullTableName()
    {
        $config = $this->getConnection()->getConfig();
        $database = $config['database'] ?? '';
        $table = $this->getTable();
        return "`{$database}`.`{$table}`";
    }
}