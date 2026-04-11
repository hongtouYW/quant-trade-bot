<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Manager extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'tbl_manager';
    protected $primaryKey = 'manager_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'manager_id',
        'manager_login',
        'manager_pass',
        'manager_name',
        'full_name',
        'agent_id',
        'state_code',
        'area_code',
        'prefix',
        'phone',
        'balance',
        'principal',
        'devicekey',
        'lastlogin_on',
        'GAstatus',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'can_view_credential',
        'status',
        'delete',
    ];

    // Hide sensitive data (e.g., password) from responses
    protected $hidden = ['manager_pass'];

    public function getAuthIdentifierName()
    {
        return 'manager_login';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->manager_pass;
    }

    public function States()
    {
        return $this->belongsTo(States::class, 'state_code', 'state_code');
    }

    public function Areas()
    {
        return $this->belongsTo(Areas::class, 'area_code', 'area_code');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function Logs()
    {
        return $this->hasMany(Log::class, 'user_id', 'manager_id')->where('log_type', 'manager');
    }

    public function shops()
    {
        return $this->hasMany(Shop::class, 'manager_id', 'manager_id')
            ->where('delete', 0);
    }

    protected static function booted()
    {
        static::created(function ($manager) {
            $manager->updateQuietly([
                'prefix' => \Prefix::create($manager->manager_id, 'manager')
            ]);
        });
    }
}
