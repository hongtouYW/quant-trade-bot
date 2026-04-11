<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $table = 'tbl_song';
    protected $primaryKey = 'song_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'song_id',
        'song_name',
        'artist_id',
        'creator',
        'album',
        'genre_id',
        'published_on',
        'status',
        'delete',
    ];
    
    public function Artist()
    {
        return $this->belongsTo(Artist::class, 'artist_id', 'artist_id');
    }

    public function Creator()
    {
        return $this->belongsTo(Artist::class, 'creator', 'artist_id');
    }

    public function Genre()
    {
        return $this->belongsTo(Genre::class, 'genre_id', 'genre_id');
    }
}
