<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Onehubxcallback extends Model
{
    protected $table = 'tbl_onehubxcallback';
    protected $primaryKey = 'response_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'response_id',
        'response',
        'error',
        'status',
        'delete',
    ];
}
