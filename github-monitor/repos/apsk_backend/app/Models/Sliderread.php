<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sliderread extends Model
{
    protected $table = 'tbl_sliderread';
    protected $primaryKey = 'sliderread_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'sliderread_id',
        'slider_id',
        'member_id',
        'read_on',
        'agent_id',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Slider()
    {
        return $this->belongsTo(Slider::class, 'slider_id', 'slider_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
