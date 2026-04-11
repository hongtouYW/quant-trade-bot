<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $table = 'tbl_promotion';
    protected $primaryKey = 'promotion_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'promotion_id',
        'promotiontype_id',
        'title',
        'promotion_desc',
        'vip_id',
        'agent_id',
        'amount',
        'percent',
        'photo',
        'newbie',
        'url',
        'lang',
        'status',
        'delete',
    ];

    public function MyVip()
    {
        return $this->belongsTo(VIP::class, 'vip_id', 'vip_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function Promotiontype()
    {
        return $this->belongsTo(Promotiontype::class, 'promotiontype_id', 'promotiontype_id');
    }
}
