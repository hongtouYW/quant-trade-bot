<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CutRealServerStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cut_real_server_id',
        'hours',
        'status',
        'namespace',
        'server'
    ];
}
