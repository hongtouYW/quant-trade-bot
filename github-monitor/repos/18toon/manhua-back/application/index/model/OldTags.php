<?php

namespace app\index\model;

use think\Model;

class OldTags extends Model
{
    protected $connection = 'old_ins_mh';
    protected $table = 'tags';
}