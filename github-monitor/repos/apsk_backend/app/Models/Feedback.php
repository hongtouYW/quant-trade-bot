<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'tbl_feedback';
    protected $primaryKey = 'feedback_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'feedback_id',
        'feedbacktype_id',
        'member_id',
        'shop_id',
        'manager_id',
        'feedback_desc',
        'photo',
        'agent_id',
        'status',
        'delete',
    ];

    public function Feedbacktype()
    {
        return $this->belongsTo(Feedbacktype::class, 'feedbacktype_id', 'feedbacktype_id');
    }

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'shop_id');
    }

    public function Manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id', 'manager_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
