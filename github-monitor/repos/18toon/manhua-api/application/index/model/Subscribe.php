<?php

namespace app\index\model;

use think\Model;

class Subscribe extends Model
{
    public function getFullTableName()
    {
        $config = $this->getConnection()->getConfig();
        $database = $config['database'] ?? '';
        $table = $this->getTable();
        return "`{$database}`.`{$table}`";
    }
}