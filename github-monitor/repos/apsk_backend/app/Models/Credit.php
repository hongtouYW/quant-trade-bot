<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    protected $table = 'tbl_credit';
    protected $primaryKey = 'credit_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'credit_id',
        'user_id',
        'member_id',
        'shop_id',
        'gamemember_id',
        'payment_id',
        'prefix',
        'orderid',
        'transactionId',
        'bankaccount_id',
        'type',
        'isqr',
        'amount',
        'before_balance',
        'after_balance',
        'reason',
        'type',
        'submit_on',
        'agent_id',
        'status',
        'delete',
    ];

    public function User()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Gamemember()
    {
        return $this->belongsTo(Gamemember::class, 'gamemember_id', 'gamemember_id');
    }

    public function Paymentgateway()
    {
        return $this->belongsTo(Paymentgateway::class, 'payment_id', 'payment_id');
    }

    public function Bankaccount()
    {
        return $this->belongsTo(Bankaccount::class, 'bankaccount_id', 'bankaccount_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    protected static function booted()
    {
        static::created(function ($credit) {
            $credit->updateQuietly([
                'prefix' => \Prefix::create($credit->credit_id, 'invoice')
            ]);
        });
    
        static::updated(function ($credit) {
            if (
                $credit->type === 'deposit' &&
                $credit->status === 1 &&
                $credit->delete === 0 &&
                $credit->wasChanged('status')
            ) {
                $member = Member::where('status',1)
                                ->where('delete',0)
                                ->where('member_id',$credit->member_id)
                                ->first();
                if (!$member) {
                    return;
                }
                $newbie = \Promotionevent::newbie( $member, 'newmemberreload' );
                $redeem = \Promotionevent::redeem( $member, 'newmemberreload', $newbie );
            }
        });
    }
}
