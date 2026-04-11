<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gameplatform extends Model
{
    use HasFactory;

    protected $table = 'tbl_gameplatform';
    protected $primaryKey = 'gameplatform_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gameplatform_id',
        'gameplatform_name',
        'api',
        'icon',
        'commission',
        'status',
        'delete',
    ];
}
