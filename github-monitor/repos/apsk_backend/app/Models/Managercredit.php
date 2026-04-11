<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Managercredit extends Model
{
    protected $table = 'tbl_managercredit';
    protected $primaryKey = 'managercredit_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'managercredit_id',
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
        static::created(function ($managercredit) {
            $managercredit->updateQuietly([
                'prefix' => \Prefix::create($managercredit->managercredit_id, 'managertrasaction')
            ]);
        });
    }
}
