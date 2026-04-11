<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tbl_user';
    protected $primaryKey = 'user_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'user_login',
        'user_pass',
        'user_name',
        'user_role',
        'state_code',
        'area_code',
        'prefix',
        'agent_id',
        'lastlogin_on',
        'GAstatus',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'status',
        'delete',
    ];

    // Hide sensitive data (e.g., password) from responses
    protected $hidden = [
        'user_pass',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function getAuthIdentifierName()
    {
        return 'user_login';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->user_pass;
    }

    // // Relationship to permissions
    // public function permissions()
    // {
    //     return $this->hasMany(Permission::class, 'permission_user', 'user_id');
    // }
    public function access()
    {
        return $this->hasMany(Access::class, 'user_role', 'user_role');
    }

    public function Roles()
    {
        return $this->belongsTo(Roles::class, 'user_role', 'role_name');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function States()
    {
        return $this->belongsTo(States::class, 'state_code', 'state_code');
    }

    public function Areas()
    {
        return $this->belongsTo(Areas::class, 'area_code', 'area_code');
    }

    /**
     * Get all modules the user has view permission for.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getViewableModules()
    {
        return $this->access()
                    ->where('can_view', true)
                    ->where('can_edit', true)
                    ->where('can_delete', true)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->with(['module' => function($query) {
                        $query->where('status', 1)->where('delete', 0);
                    }])
                    ->get()
                    ->map(function ($access) {
                        return $access->module;
                    })
                    ->filter()
                    ->unique('module_id');
    }

    public function getNameAttribute()
    {
        return $this->user_name;
    }

    public function adminlte_profile_url()
    {
        return '/setup'; // your setup page
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $number = str_pad($user->user_id, 7, '0', STR_PAD_LEFT);
            $user->updateQuietly([
                'prefix' => \Prefix::create($number, 'admin'),
            ]);
        });
    }
}
