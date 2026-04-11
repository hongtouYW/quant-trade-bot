<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Officialhome extends Model
{
    protected $table = 'tbl_officialhome';
    protected $primaryKey = 'officialhome_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'officialhome_id',
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
