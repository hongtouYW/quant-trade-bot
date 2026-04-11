<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shop extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'tbl_shop';
    protected $primaryKey = 'shop_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'shop_login',
        'shop_pass',
        'shop_name',
        'shop_code',
        'area_code',
        'principal',
        'prefix',
        'balance',
        'devicekey',
        'lastlogin_on',
        'GAstatus',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'can_deposit',
        'can_withdraw',
        'can_create',
        'can_block',
        'can_income',
        'can_view_credential',
        'lowestbalance',
        'lowestbalance_on',
        'no_withdrawal_fee',
        'read_clear',
        'alarm',
        'reason',
        'manager_id',
        'user_id',
        'agent_id',
        'status',
        'delete',
    ];

    // Hide sensitive data (e.g., password) from responses
    protected $hidden = ['shop_pass'];

    public function getAuthIdentifierName()
    {
        return 'shop_login';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->shop_pass;
    }

    public function Areas()
    {
        return $this->belongsTo(Areas::class, 'area_code', 'area_code');
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

    public function logs()
    {
        return $this->hasMany(Log::class, 'user_id', 'shop_id')->where('log_type', 'shop');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'shop_id', 'shop_id')->where('delete', 0);
    }

    public function shopcredits()
    {
        return $this->hasMany(Shopcredit::class, 'shop_id', 'shop_id');
    }

    public static function newcode(int $length = 8)
    {
        do {
            // Generate random alphanumeric string
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $shop_code = '';
            for ($i = 0; $i < $length; $i++) {
                $shop_code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('shop_code', $shop_code)->exists()); // ensure uniqueness
        return $shop_code;
    }

    protected static function booted()
    {
        static::created(function ($shop) {
            $shop->updateQuietly([
                'prefix' => \Prefix::create($shop->shop_id, 'shop'),
                'shop_code' => self::newcode(),
            ]);
        });
    }
}
