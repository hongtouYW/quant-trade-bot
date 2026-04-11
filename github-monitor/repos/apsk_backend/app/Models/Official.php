<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Official extends Model
{
    protected $table = 'tbl_official';
    protected $primaryKey = 'official_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'official_id',
        'url',
        'type',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
