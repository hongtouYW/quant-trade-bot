<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPBlock extends Model
{
    protected $table = 'tbl_ipblock';
    protected $primaryKey = 'ipblock_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'ip',
        'reason',
        'agent_id',
        'status',
        'delete',
    ];

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    protected static function booted()
    {
        static::created(function ($ipblock) {
            // IP Block Access
            if ($ipblock->status == 1 && $ipblock->delete == 0) {
                Member::where('ip', $ipblock->ip)
                    ->where('status', '!=', 0)
                    ->where(function($query) use ($ipblock) {
                        $query->where('agent_id', $ipblock->agent_id)
                            ->orWhere(function($q) use ($ipblock) {
                                if ($ipblock->agent_id == 0) {
                                    $q->whereNull('agent_id');
                                } else {
                                    $q->whereRaw('0 = 1'); // effectively exclude
                                }
                            });
                    })
                    ->update(['status' => 0]);
            }
        });
        static::updated(function ($ipblock) {
            // IP Block Access
            if ($ipblock->status == 1 && $ipblock->delete == 0) {
                Member::where('ip', $ipblock->ip)
                    ->where('status', '!=', 0)
                    ->where(function($query) use ($ipblock) {
                        $query->where('agent_id', $ipblock->agent_id)
                            ->orWhere(function($q) use ($ipblock) {
                                if ($ipblock->agent_id == 0) {
                                    $q->whereNull('agent_id');
                                } else {
                                    $q->whereRaw('0 = 1'); // effectively exclude
                                }
                            });
                    })
                    ->update(['status' => 0]);
            }
        });
    }
}
