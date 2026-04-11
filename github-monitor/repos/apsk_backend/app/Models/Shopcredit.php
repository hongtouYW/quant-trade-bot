<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shopcredit extends Model
{
    protected $table = 'tbl_shopcredit';
    protected $primaryKey = 'shopcredit_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'shopcredit_id',
        'shop_id',
        'manager_id',
        'user_id',
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

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id', 'manager_id');
    }

    public function User()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    protected static function booted()
    {
        static::created(function ($shopcredit) {
            $shopcredit->updateQuietly([
                'prefix' => \Prefix::create($shopcredit->shopcredit_id, 'shoptrasaction')
            ]);
        });
    }
}
