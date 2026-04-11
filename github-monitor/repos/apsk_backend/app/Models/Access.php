<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    protected $table = 'tbl_access';
    protected $primaryKey = 'access_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'access_id',
        'user_role',
        'module_id',
        'can_view',
        'can_edit',
        'can_delete',
        'status',
        'delete',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'module_id');
    }
}
