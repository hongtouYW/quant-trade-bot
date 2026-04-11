<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotiontype extends Model
{
    protected $table = 'tbl_promotiontype';
    protected $primaryKey = 'promotiontype_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'promotiontype_id',
        'promotion_type',
        'event',
        'agent_id',
        'amount',
        'status',
        'delete',
    ];
}
