<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Countries extends Model
{
    use HasFactory;

    protected $table = 'tbl_countries';
    protected $primaryKey = 'country_code';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'country_code',
        'phone_code',
        'country_name',
        'status',
        'delete',
    ];

    public function getCountryCodeAttribute($value)
    {
        return $value;
    }

    protected static function boot()
    {
        parent::boot();
    }

}
