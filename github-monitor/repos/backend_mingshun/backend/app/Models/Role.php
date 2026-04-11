<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    public const SELECT = 'name';
    public const TITLE = '角色';
    public const CRUD_ROUTE_PART = 'roles';
    public $timestamps = false;
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }

    public function scopeActive($query)
    {
        $query->where('id', '!=' , 999);
        return $query;
    }
}
