<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedbacktype extends Model
{
    protected $table = 'tbl_feedbacktype';
    protected $primaryKey = 'feedbacktype_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'feedbacktype_id',
        'title',
        'feedback_type',
        'feedback_desc',
        'type',
        'status',
        'delete',
    ];
}
