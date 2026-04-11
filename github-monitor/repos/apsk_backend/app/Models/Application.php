<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'tbl_application';
    protected $primaryKey = 'application_id';
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'updated_on';

    // Allow mass assignment for these fields
    protected $fillable = [
        'application_id',
        'application_name',
        'platform',
        'version',
        'latest_version',
        'minimun_version',
        'download_url',
        'type',
        'changelog',
        'force_update',
        'status',
        'delete',
    ];
}
