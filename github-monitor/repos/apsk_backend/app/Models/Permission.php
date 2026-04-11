<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Module;

class Permission extends Model
{
    protected $table = 'tbl_permission';
    protected $primaryKey = 'permission_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'permission_user',
        'user_type',
        'permission_target',
        'target_type',
        'can_view',
        'can_edit',
        'can_delete',
        'status',
        'delete',
    ];

    public function PermissionUser()
    {
        return $this->morphTo(__FUNCTION__, 'user_type', 'permission_user');
    }

    public function PermissionTarget()
    {
        return $this->morphTo(__FUNCTION__, 'target_type', 'permission_target');
    }

    public function toArray()
    {
        $data = parent::toArray();
        if (!empty($data['permission_user']) && !empty($this->user_type)) {
            $key = ucfirst($this->user_type); // manager, user, shop, member
            $data[$key] = $data['permission_user'];
            unset($data['permission_user']);
        }
        if (!empty($data['permission_target']) && !empty($this->target_type)) {
            $key = ucfirst($this->target_type); // manager, user, shop, member
            $data[$key] = $data['permission_target'];
            unset($data['permission_target']);
        }
        return $data;
    }
}
