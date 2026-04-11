<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gametype extends Model
{
    use HasFactory;

    protected $table = 'tbl_gametype';
    protected $primaryKey = 'gametype_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gametype_id',
        'type_name',
        'type_desc',
        'status',
        'delete',
    ];
}
