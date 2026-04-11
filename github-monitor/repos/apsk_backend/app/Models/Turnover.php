<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turnover extends Model
{
    protected $table = 'tbl_turnover';
    protected $primaryKey = 'turnover_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'turnover_id',
        'member_id',
        'amount',
        'winamount',
        'loseamount',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }
}
