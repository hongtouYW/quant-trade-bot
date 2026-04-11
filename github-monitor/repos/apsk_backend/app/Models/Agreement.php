<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    protected $table = 'tbl_agreement';
    protected $primaryKey = 'agreement_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'agreement_id',
        'title',
        'picture',
        'desc',
        'url',
        'lang',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
