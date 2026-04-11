<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Areas extends Model
{
    use HasFactory;

    protected $table = 'tbl_areas';
    protected $primaryKey = 'area_code';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'area_code',
        'country_code',
        'state_code',
        'area_name',
        'area_type',
        'postal_code',
        'agent_id',
        'status',
        'delete',
    ];

    public function Countries()
    {
        return $this->belongsTo(Countries::class, 'country_code', 'country_code');
    }

    public function States()
    {
        return $this->belongsTo(States::class, 'state_code', 'state_code');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status',1)->where('delete',0);
    }

    public static function filterByCountry($country_code, $agent_id = null)
    {
        return self::active()
            ->where('status', 1)
            ->where('delete', 0)
            ->where('country_code', $country_code)
            ->when(
                is_null($agent_id),
                fn($q) => $q->where('agent_id', 0),
                fn($q) => $q->where('agent_id', $agent_id)
            )
            ->orderBy('area_name')
            ->get();
    }

    public static function filterByState($state_code, $agent_id = null)
    {
        return self::active()
            ->where('status', 1)
            ->where('delete', 0)
            ->where('state_code', $state_code)
            ->when(
                is_null($agent_id),
                fn($q) => $q->where('agent_id', 0),
                fn($q) => $q->where('agent_id', $agent_id)
            )
            ->orderBy('area_name')
            ->get();
    }
}
