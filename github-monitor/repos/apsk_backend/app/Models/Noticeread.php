<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticeread extends Model
{
    protected $table = 'tbl_noticeread';
    protected $primaryKey = 'noticeread_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'noticeread_id',
        'notice_id',
        'member_id',
        'shop_id',
        'manager_id',
        'read_on',
        'agent_id',
        'status',
        'delete',
    ];

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

    public function Noticepublic()
    {
        return $this->belongsTo(Noticepublic::class, 'notice_id', 'notice_id');
    }

    public function Agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
