<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $table = 'tbl_question';
    protected $primaryKey = 'question_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'question_id',
        'question_type',
        'type',
        'title',
        'question_desc',
        'picture',
        'related',
        'lang',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function Myrelated()
    {
        return $this->belongsTo(Question::class, 'related', 'question_id');
    }
}
