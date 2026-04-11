<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gamemember extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'tbl_gamemember';
    protected $primaryKey = 'gamemember_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gamemember_id',
        'game_id',
        'gameplatform_id',
        'provider_id',
        'member_id',
        'shop_id',
        'uid',
        'loginId',
        'login',
        'pass',
        'name',
        'paymentpin',
        'balance',
        'has_balance',
        'lastlogin_on',
        'lastsync_on',
        'status',
        'delete',
    ];

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Game()
    {
        return $this->belongsTo(Game::class, 'game_id', 'game_id');
    }

    public function Gameplatform()
    {
        return $this->belongsTo(Gameplatform::class, 'gameplatform_id', 'gameplatform_id');
    }

    public function Provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'provider_id');
    }

}
