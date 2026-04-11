<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'tbl_log';
    protected $primaryKey = 'log_id';
    const CREATED_AT = 'created_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'log_id',
        'user_id',
        'area_code',
        'log_type',
        'log_text',
        'log_desc',
        'log_api',
        'location',
        'device',
        'agent_id',
    ];
    
    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
