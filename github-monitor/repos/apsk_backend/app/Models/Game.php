<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Game extends Model
{
    use HasFactory;

    protected $table = 'tbl_game';
    protected $primaryKey = 'game_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'game_id',
        'gameplatform_id',
        'game_name',
        'game_desc',
        'provider_id',
        'gametarget_id',
        'tag_id',
        'android',
        'ios',
        'icon',
        'icon_zh',
        'banner',
        'api',
        'type',
        'status',
        'delete',
    ];

    public function Gameplatform()
    {
        return $this->belongsTo(Gameplatform::class, 'gameplatform_id', 'gameplatform_id');
    }

    public function Provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'provider_id');
    }

    public function gameType()
    {
        return $this->belongsTo(Gametype::class, 'type', 'gametype_id');
    }

    public function Gamebookmark()
    {
        return $this->belongsTo(Gamebookmark::class, 'game_id', 'game_id');
    }
}
