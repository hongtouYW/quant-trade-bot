<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provider extends Model
{
    use HasFactory;

    protected $table = 'tbl_provider';
    protected $primaryKey = 'provider_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'provider_id',
        'gameplatform_id',
        'providertarget_id',
        'provider_name',
        'provider_category',
        'android',
        'ios',
        'icon',
        'icon_sm',
        'banner',
        'download',
        'platform_type',
        'status',
        'delete',
    ];

    public function Gameplatform()
    {
        return $this->belongsTo(Gameplatform::class, 'gameplatform_id', 'gameplatform_id');
    }
}
