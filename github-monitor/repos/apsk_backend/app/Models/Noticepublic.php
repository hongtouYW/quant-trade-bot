<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticepublic extends Model
{
    protected $table = 'tbl_noticepublic';
    protected $primaryKey = 'notice_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'notice_id',
        'recipient_type',
        'title',
        'desc',
        'lang',
        'start_on',
        'end_on',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
