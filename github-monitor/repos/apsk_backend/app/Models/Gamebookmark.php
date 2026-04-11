<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gamebookmark extends Model
{
    protected $table = 'tbl_gamebookmark';
    protected $primaryKey = 'gamebookmark_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gamebookmark_id',
        'gamebookmark_name',
        'game_id',
        'member_id',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Game()
    {
        return $this->belongsTo(Game::class, 'game_id', 'game_id');
    }
}
