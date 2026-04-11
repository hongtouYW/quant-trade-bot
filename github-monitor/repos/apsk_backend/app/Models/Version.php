<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    protected $table = 'tbl_version';
    protected $primaryKey = 'id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'id',
        'application_id',
        'type',
    ];

    public function Application()
    {
        return $this->belongsTo(Application::class, 'application_id', 'application_id');
    }
}
