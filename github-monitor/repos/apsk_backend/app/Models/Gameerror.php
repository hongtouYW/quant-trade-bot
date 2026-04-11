<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gameerror extends Model
{
    protected $table = 'tbl_gameerror';
    protected $primaryKey = 'gameerror_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gameerror_id',
        'api',
        'request',
        'response',
        'status',
        'delete',
    ];
}
