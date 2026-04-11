<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Providerbookmark extends Model
{
    protected $table = 'tbl_providerbookmark';
    protected $primaryKey = 'providerbookmark_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'providerbookmark_id',
        'providerbookmark_name',
        'provider_id',
        'member_id',
        'status',
        'delete',
    ];
    
    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'provider_id');
    }
}
