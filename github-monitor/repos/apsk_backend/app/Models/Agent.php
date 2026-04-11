<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'tbl_agent';
    protected $primaryKey = 'agent_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'agent_id',
        'agent_code',
        'agent_name',
        'country_code',
        'state_code',
        'title',
        'upline',
        'master',
        'type',
        'url',
        'isChatAccountCreate',
        'support',
        'telegramsupport',
        'whatsappsupport',
        'balance',
        'principal',
        'icon',
        'status',
        'delete',
    ];

    public function Countries()
    {
        return $this->belongsTo(Countries::class, 'country_code', 'country_code');
    }

    public function States()
    {
        return $this->belongsTo(States::class, 'state_code', 'state_code');
    }

    public function Myupline()
    {
        return $this->belongsTo(Agent::class, 'upline', 'agent_id');
    }

    public function Mymaster()
    {
        return $this->belongsTo(Agent::class, 'master', 'agent_id');
    }

    public function Downlines()
    {
        return $this->hasMany(Agent::class, 'upline', 'agent_id');
    }

    public static function newcode(int $length = 8)
    {
        do {
            // Generate random alphanumeric string
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $agent_code = '';
            for ($i = 0; $i < $length; $i++) {
                $agent_code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('agent_code', $agent_code)->exists()); // ensure uniqueness
        return $agent_code;
    }

    protected static function booted()
    {
        static::created(function ($agent) {
            $agent->updateQuietly([
                'agent_code' => self::newcode(),
            ]);
        });

        static::updated(function ($agent) {
            if ( $agent->wasChanged('status') )
            {
                User::where('agent_id', $agent->agent_id)
                    ->update([
                        'status' => $agent->status,
                        'updated_on' => now(),
                    ]);
            }
            if ( $agent->wasChanged('delete') && $agent->delete == 1 )
            {
                User::where('agent_id', $agent->agent_id)
                    ->update([
                        'status' => 0,
                        'delete' => 1,
                        'updated_on' => now(),
                    ]);
            }
        });
    }
}
