<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gamepoint extends Model
{
    use HasFactory;

    protected $table = 'tbl_gamepoint';
    protected $primaryKey = 'gamepoint_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gamepoint_id',
        'shop_id',
        'gamemember_id',
        'prefix',
        'orderid',
        'type',
        'ip',
        'amount',
        'before_balance',
        'after_balance',
        'start_on',
        'end_on',
        'agent_id',
        'status',
        'delete',
    ];

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Gamemember()
    {
        return $this->belongsTo(Gamemember::class, 'gamemember_id', 'gamemember_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    protected static function booted()
    {
        static::created(function ($gamepoint) {
            $gamepoint->updateQuietly([
                'prefix' => \Prefix::create($gamepoint->gamepoint_id, 'point')
            ]);
            if (
                $gamepoint->type === 'reload' &&
                $gamepoint->status === 1 &&
                $gamepoint->delete === 0
            ) {
                $gamemember = Gamemember::where('status',1)
                                ->where('delete',0)
                                ->where('member_id',$gamepoint->gamemember_id)
                                ->first();
                if (!$gamemember) {
                    return;
                }
                $member = Member::where('status',1)
                                ->where('delete',0)
                                ->where('member_id',$gamemember->member_id)
                                ->first();
                if (!$member) {
                    return;
                }
                $newbie = \Promotionevent::newbie( $member, 'newmembergamereload' );
                $redeem = \Promotionevent::redeem( $member, 'newmembergamereload', $newbie );
            }
        });
    }
}
