<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Module;

class Performance extends Model
{
    protected $table = 'tbl_performance';
    protected $primaryKey = 'id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'member_id',
        'downline',
        'upline',
        'commissionrank_id',
        'performance_date',
        'sales_amount',
        'commission_amount',
        'before_balance',
        'after_balance',
        'notes',
        'agent_id',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Mydownline()
    {
        return $this->belongsTo(Member::class, 'downline', 'member_id');
    }

    public function Myupline()
    {
        return $this->belongsTo(Member::class, 'upline', 'member_id');
    }

    public function Commissionrank()
    {
        return $this->belongsTo(Commissionrank::class, 'commissionrank_id', 'commissionrank_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
