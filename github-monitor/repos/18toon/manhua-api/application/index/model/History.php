<?php

namespace app\index\model;

use think\Model;

class History extends Model
{

    public static function add_log($member_id, $manhua_id, $capter_id)
    {
        $is_play_id = self::where('member_id', '=', $member_id)
            ->where('manhua_id', '=', $manhua_id)
            ->where('capter_id', '=', $capter_id)
            ->value('id');
    
        $time = time();
    
        if ($is_play_id) {
            $update = [
                'addtime' => $time
            ];
            self::where('id', '=', $is_play_id)->update($update);
        } else {
            $add = [
                'member_id' => $member_id,
                'manhua_id' => $manhua_id,
                'capter_id' => $capter_id,
                'addtime' => $time,
            ];
            self::insert($add);
        }
    
        Manhua::where('id', $manhua_id)->inc('view')->inc('real_view')->inc('monthly_view')->update();
        Chapter::where('id', $capter_id)->inc('view')->inc('real_view')->inc('monthly_view')->update();
    }

    public function getFullTableName()
    {
        $config = $this->getConnection()->getConfig();
        $database = $config['database'] ?? '';
        $table = $this->getTable(); 
        return "`{$database}`.`{$table}`";
    }
    
}
