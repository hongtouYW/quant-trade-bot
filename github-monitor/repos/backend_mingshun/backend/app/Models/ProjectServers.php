<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectServers extends Model
{
    use HasFactory;

    public const TITLE = '服务器';
    public const CRUD_ROUTE_PART = 'pservers';
}
