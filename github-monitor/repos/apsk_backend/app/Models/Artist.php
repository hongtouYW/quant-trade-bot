<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Artist extends Model
{
    protected $table = 'tbl_artist';
    protected $primaryKey = 'artist_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'artist_id',
        'artist_name',
        'artist_desc',
        'genre_id',
        'country_code',
        'dob',
        'status',
        'delete',
    ];
    
    public function Genre()
    {
        return $this->belongsTo(Genre::class, 'genre_id', 'genre_id');
    }

    public function Countries()
    {
        return $this->belongsTo(Countries::class, 'country_code', 'country_code');
    }
}
