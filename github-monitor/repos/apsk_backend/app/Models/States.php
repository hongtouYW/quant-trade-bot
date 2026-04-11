<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class States extends Model
{
    use HasFactory;

    protected $table = 'tbl_states';
    protected $primaryKey = 'state_code';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'state_code',
        'state_name',
        'status',
        'delete',
    ];

    public function getStateCodeAttribute($value)
    {
        return $value;
    }

    protected static function boot()
    {
        parent::boot();
    }

    public function scopeActive($query)
    {
        return $query->where('status',1)->where('delete',0);
    }

    public function myareas()
    {
        return $this->hasMany(Areas::class, 'state_code', 'state_code')
                    ->where('status', 1)
                    ->where('delete', 0);
    }

    public static function filterByCountry($country_code)
    {
        return self::active()
            ->whereHas('myareas', function ($q) use ($country_code) {
                $q->where('country_code', $country_code)
                ->where('status', 1)
                ->where('delete', 0);
            })
            ->orderBy('state_name')
            ->get();
    }
}
