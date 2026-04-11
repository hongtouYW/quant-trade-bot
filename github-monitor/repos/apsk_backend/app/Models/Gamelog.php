<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gamelog extends Model
{
    protected $table = 'tbl_gamelog';
    protected $primaryKey = 'gamelog_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gamelog_id',
        'gamelogtarget_id',
        'gamemember_id',
        'game_id',
        'tableid',
        'before_balance',
        'after_balance',
        'betamount',
        'winloss',
        'remark',
        'error',
        'startdt',
        'enddt',
        'agent_id',
        'status',
        'delete',
    ];

    public function Gamemember()
    {
        return $this->belongsTo(Gamemember::class, 'gamemember_id', 'gamemember_id');
    }

    public function Game()
    {
        return $this->belongsTo(Game::class, 'game_id', 'game_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

}
