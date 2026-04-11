<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'tbl_module';
    protected $primaryKey = 'module_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'module_id',
        'module_name',
        'module_desc',
        'section',
        'controller',
        'has_tab',
        'tab_main',
        'status',
        'delete',
    ];
}
