<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agentcredit extends Model
{
    protected $table = 'tbl_agentcredit';
    protected $primaryKey = 'agentcredit_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'agentcredit_id',
        'agent_id',
        'user_id',
        'manager_id',
        'shop_id',
        'member_id',
        'prefix',
        'amount',
        'before_balance',
        'after_balance',
        'type',
        'reason',
        'submit_on',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function User()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function Manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id', 'manager_id');
    }

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    protected static function booted()
    {
        static::created(function ($agentcredit) {
            $agentcredit->updateQuietly([
                'prefix' => \Prefix::create($agentcredit->agentcredit_id, 'agenttrasaction')
            ]);
        });
    }
}
