<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitationhistory extends Model
{
    protected $table = 'tbl_invitation_history';
    protected $primaryKey = 'invitation_history_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'invitation_history_id',
        'invitecode',
        'member_id',
        'upline',
        'agent_id',
        'registered_on',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Myupline()
    {
        return $this->belongsTo(Member::class, 'upline', 'member_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Areas::class, 'agent_id', 'agent_id');
    }
}
