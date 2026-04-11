<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Genre extends Model
{
    protected $table = 'tbl_genre';
    protected $primaryKey = 'genre_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'genre_id',
        'genre_name',
        'status',
        'delete',
    ];
}
