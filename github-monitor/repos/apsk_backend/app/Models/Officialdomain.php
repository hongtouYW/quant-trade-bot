<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Officialdomain extends Model
{
    protected $table = 'tbl_officialdomain';
    protected $primaryKey = 'officialdomain_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'officialdomain_id',
        'official_id',
        'url',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function Official()
    {
        return $this->belongsTo(Official::class, 'official_id', 'official_id');
    }
}
