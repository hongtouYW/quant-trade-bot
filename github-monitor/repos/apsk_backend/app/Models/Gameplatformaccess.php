<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Gameplatformaccess extends Model
{
    use HasFactory;

    protected $table = 'tbl_gameplatformaccess';
    protected $primaryKey = 'gameplatformaccess_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'gameplatform_id',
        'agent_id',
        'commission',
        'can_use',
        'status',
        'delete',
    ];

    public function Gameplatform()
    {
        return $this->belongsTo(Gameplatform::class, 'gameplatform_id', 'gameplatform_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
