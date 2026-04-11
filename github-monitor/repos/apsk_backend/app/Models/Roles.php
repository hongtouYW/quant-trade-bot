<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    protected $table = 'tbl_role';
    protected $primaryKey = 'role_name';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'role_name',
        'role_desc',
        'status',
        'delete',
    ];
}
