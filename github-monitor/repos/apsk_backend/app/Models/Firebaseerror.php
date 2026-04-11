<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Firebaseerror extends Model
{
    protected $table = 'tbl_firebaseerror';
    protected $primaryKey = 'firebaseerror_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'firebaseerror_id',
        'request',
        'response',
        'status',
        'delete',
    ];
}
