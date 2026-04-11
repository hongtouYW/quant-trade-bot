<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recruit extends Model
{
    protected $table = 'tbl_recruit';
    protected $primaryKey = 'recruit_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'recruit_id',
        'member_id',
        'title',
        'upline',
        'invitecode',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Myupline()
    {
        return $this->belongsTo(Recruit::class, 'upline', 'member_id');
    }

    public function Downlines()
    {
        return $this->hasMany(Recruit::class, 'upline', 'member_id');
    }

    public function DownlinesRecursive()
    {
        return $this->hasMany(Recruit::class, 'upline', 'member_id')
                    ->with('Member', 'DownlinesRecursive'); // recursively load all levels
    }

    public static function newcode(int $length = 8)
    {
        do {
            // Generate random alphanumeric string
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $invitecode = '';
            for ($i = 0; $i < $length; $i++) {
                $invitecode .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('invitecode', $invitecode)->exists()); // ensure uniqueness
        return $invitecode;
    }

    protected static function booted()
    {
        static::created(function ($recruit) {
            $member = Member::where('status',1)
                            ->where('delete',0)
                            ->where('member_id',$recruit->upline)
                            ->first();
            if (!$member) {
                return;
            }
            $redeem = \Promotionevent::redeem( $member, 'newmemberrecruit', true );
        });
    }
}
