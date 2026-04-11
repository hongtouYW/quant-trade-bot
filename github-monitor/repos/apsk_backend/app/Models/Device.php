<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'tbl_device';
    protected $primaryKey = 'device_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'device_id',
        'devicemeta',
        'member_id',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
