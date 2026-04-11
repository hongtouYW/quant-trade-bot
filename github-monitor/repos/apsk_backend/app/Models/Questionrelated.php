<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Questionrelated extends Model
{
    protected $table = 'tbl_questionrelated';
    protected $primaryKey = 'questionrelated_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'questionrelated_id',
        'question_id',
        'title',
        'question_desc',
        'picture',
        'lang',
        'status',
        'delete',
    ];

    public function MyQuestion()
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
}
