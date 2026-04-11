<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'tbl_invitation';
    protected $primaryKey = 'invitation_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';
    
    // Allow mass assignment for these fields
    protected $fillable = [
        'invitation_id',
        'invitecode',
        'invitecode_name',
        'member_id',
        'agent_id',
        'status',
        'delete',
    ];

    public function Member()
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Areas::class, 'agent_id', 'agent_id');
    }

    public static function newcode(int $length = 8)
    {
        do {
            // Generate random alphanumeric string
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $invitecode = '';
            for ($i = 0; $i < $length; $i++) {
                $invitecode .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('invitecode', $invitecode)->exists()); // ensure uniqueness
        return $invitecode;
    }
}
