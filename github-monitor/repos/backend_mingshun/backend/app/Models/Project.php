<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Project extends Model
{
    use Log;
    use HasFactory;
    protected $fillable = [
        'name',
        'enable_4k',
        'token',
        'direct_cut',
        'daily_cut',
        'daily_cut_quota',
        'redis_db',
        'enable_photo',
        'solo'
    ];
    public const TITLE = '项目';
    public const CRUD_ROUTE_PART = 'projects';
    public const SELECT = 'name';
    public const MINGSHUN = 18;
    public const SHORTSTORY = 36;

    public const YESNO = [
        '0' => '关闭',
        '1' => '开启',      
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_users');
    }

    public function servers()
    {
        return $this->hasMany(ProjectServers::class);
    }
    
    public function scopeSearch($query, Request $request)
    {
        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->id);
            });
        }

        return $query;
    }

    public function scopeActive($query)
    {
        return $query;
    }
}
