<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Trait\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectTypes extends Model
{
    use Log;
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'project_id',
        'show_name',
        'is_show'
    ];
    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];
    public const TITLE = '主题';
    public const CRUD_ROUTE_PART = 'ptypes';
    public const SELECT = 'name';

    public function scopeProject($query, $id)
    {
        $query->where('project_id', $id);
    }

    public function scopeActive($query)
    {
        $query->where('status', 1);
        return $query;
    }

    public function scopeSearch($query, Request $request)
    {
        if ($request->status !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->id);
            });
        }

        if(Auth::user()->isSuperAdmin()){
            if ($request->project_id !== null) {
                $query->where(function ($q) use ($request) {
                    $q->where('project_id', $request->project_id);
                });
            }
        }else{
            $projects = Auth::user()->projects->first();
            $query->where('project_id', $projects->id);
            if(Auth::user()->checkUserRole([3])){
                $query->active();
            }
        }
        return $query;
    }

    public function projects()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function videos()
    {
        return $this->belongsToMany(
            Video::class,
            'video_choose_project_types',
            'project_types_id',
            'video_id'
        );
    }

    protected static function booted()
    {
        static::deleting(function ($projectType) {
            $projectType->videos()->detach();
        });
    }
}
