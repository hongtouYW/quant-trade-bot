<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Member extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'tbl_member';
    protected $primaryKey = 'member_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'member_id',
        'member_login',
        'member_pass',
        'member_name',
        'full_name',
        'area_code',
        'prefix',
        'phone',
        'email',
        'wechat',
        'whatsapp',
        'facebook',
        'telegram',
        'avatar',
        'balance',
        'devicekey',
        'devicemeta',
        'ip',
        'vip',
        'shop_id',
        'agent_id',
        'dob',
        'alarm',
        'reason',
        'lastlogin_on',
        'GAstatus',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'bindphone',
        'bindemail',
        'bindgoogle',
        'status',
        'delete',
    ];

    // Hide sensitive data (e.g., password) from responses
    protected $hidden = ['member_pass'];

    public function getAuthIdentifierName()
    {
        return 'member_login';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->member_pass;
    }

    public function Areas()
    {
        return $this->belongsTo(Areas::class, 'area_code', 'area_code');
    }

    public function MyVip()
    {
        return $this->belongsTo(VIP::class, 'vip', 'vip_id');
    }

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Score()
    {
        return $this->belongsTo(Score::class, 'member_id', 'member_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function credits()
    {
        return $this->hasMany(Credit::class, 'member_id', 'member_id');
    }

    public function Logs()
    {
        return $this->hasMany(Log::class, 'user_id', 'member_id')->where('log_type', 'member');
    }

    public static function isIpBlocked($ip, $agent_id)
    {
        $agent_id = $agent_id ? $agent_id : 0;
        return IPBlock::where('ip', $ip)
                    ->where('status', 1)
                    ->where('delete', 0)
                    ->where('agent_id', $agent_id)
                    ->exists();
    }

    protected static function handleDevice($member)
    {
        $deviceMeta = trim((string) $member->devicemeta);
        if ($deviceMeta === '') {
            return;
        }
        Device::firstOrCreate(
            [
                'devicemeta' => $deviceMeta,
                'member_id'  => $member->member_id,
            ],
            [
                'status' => 1,
                'delete' => 0,
            ]
        );
        // 🔥 Enforce max 10 rows (hard delete oldest)
        $deviceIdsToKeep = Device::where('member_id', $member->member_id)
            ->orderBy('created_on', 'desc')
            ->limit(10)
            ->pluck('device_id')
            ->toArray();

        Device::where('member_id', $member->member_id)
            ->whereNotIn('device_id', $deviceIdsToKeep)
            ->delete(); // hard delete
    }

    protected static function booted()
    {
        static::created(function ($member) {
            self::handleDevice($member);
            // IP Block Register
            if ($member->ip && self::isIpBlocked($member->ip, $member->agent_id)) {
                $member->updateQuietly([
                    'status' => 0,
                    'updated_on' => now(),
                ]);
                throw new \Exception(__('member.ip_restrict'));
            }
            if ( $member->agent_id ) {
                $tbl_vip = VIP::where('status',1)
                              ->where('delete',0)
                              ->where('lvl',0)
                              ->where('agent_id',$member->agent_id)
                              ->first();
                $vip_id = $tbl_vip ? $tbl_vip->vip_id : 0;
            } else {
                $vip_id = 0;
            }
            $member->updateQuietly([
                'vip' => $vip_id,
                'prefix' => \Prefix::create($member->member_id, 'member')
            ]);
            // Promotion New Register Redeen 
            $redeem = \Promotionevent::redeem( $member, 'newmemberregister', true );
        });

        static::updated(function ($member) {
            // ✅ Check if devicemeta changed
            if ($member->wasChanged('devicemeta')) {
                self::handleDevice($member);
            }
            // IP Block Access
            if ($member->ip && self::isIpBlocked($member->ip, $member->agent_id)) {
                $member->updateQuietly([
                    'status' => 0,
                    'updated_on' => now(),
                ]);
                throw new \Exception(__('member.ip_restrict'));
            }
        });
    }
}
