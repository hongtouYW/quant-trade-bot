<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recruittutorial extends Model
{
    protected $table = 'tbl_recruittutorial';
    protected $primaryKey = 'recruittutorial_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'recruittutorial_id',
        'title',
        'picture',
        'slogan',
        'desc',
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
