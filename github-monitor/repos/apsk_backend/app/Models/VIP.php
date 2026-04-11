<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VIP extends Model
{
    protected $table = 'tbl_vip';
    protected $primaryKey = 'vip_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'vip_id',
        'vip_name',
        'vip_desc',
        'lvl',
        'type',
        'reward',
        'icon',
        'firstbonus',
        'dailybonus',
        'weeklybonus',
        'monthlybonus',
        'min_amount',
        'max_amount',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
