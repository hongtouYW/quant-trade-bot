<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chatboterror extends Model
{
    protected $table = 'tbl_chatboterror';
    protected $primaryKey = 'chatboterror_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'api',
        'request',
        'response',
        'status',
        'delete',
    ];
}
