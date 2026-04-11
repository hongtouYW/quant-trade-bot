<?php

namespace App\Models;

use App\Http\Helper;
use App\Trait\LogWithManyToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class User extends Authenticatable
{
    use LogWithManyToMany;
    use HasFactory;

    protected $fillable = [
        'username',
        'password',
        'plain_password',
        'status',
        'type',
        'is_daily_press',
        'is_extra_press',
        'daily_quest',
        'daily_quest2',
        'extra_quest'
    ];

    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];

    public const PRESS = [
        '0' => '未接',
        '1' => '已接',    
    ];

    public const TYPE = [
        '1' => '视频',    
        '2' => '帖子',    
        '3' => '全部',    
    ];

    public const BELONGTOMANY = [
        'role', 'projects'
    ];

    protected $hidden = ['password', 'plain_password'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->isDirty('password') && $model->password) {
                $plainPassword = $model->password;
                $model->password = bcrypt($plainPassword);
                $model->plain_password = $plainPassword;
            }
        });
    }

    public const TITLE = '用户';
    public const CRUD_ROUTE_PART = 'users';
    public const SELECT = 'username';

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    public function scopeSearch($query, Request $request)
    {
        $query->whereHas('role', function ($q) use ($request) {
            $q->where("roles.id",'!=', 999);
        });

        if ($request->role_id !== null) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where("roles.id", $request->role_id);
            });
        }

        if ($request->project_id !== null) {
            $query->whereHas('projects', function ($q) use ($request) {
                $q->where("projects.id", $request->project_id);
            });
        }

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

        return $query;
    }

    public function scopeActive($query)
    {
        $query->where('status', 1);
        return $query;
    }
    
    public function videos()
    {
        return $this->hasMany(Video::class,'uploader');
    }

    public function firstApprove()
    {
        return $this->hasMany(Video::class,'first_approved_by');
    }

    public function coverAssigned()
    {
        return $this->hasMany(Video::class,'cover_assigned_to');
    }

    public function assigned()
    {
        return $this->hasMany(Video::class,'assigned_to');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_users');
    }

    public function ftps()
    {
        return $this->hasMany(Ftp::class);
    }

    // 1:超级管理员，2:分类主管，3:上传手，4:审核手，5:项目运营，6:项目主管, 7:图片手
    public function role()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function getCachedRoles()
    {
        $redis = Redis::connection('default');
        $cacheKey = "user_roles_{$this->id}";
        if ($redis->exists($cacheKey)) {
            return json_decode($redis->get($cacheKey), true);
        }
        $roles = $this->role()->pluck('id')->toArray();
        $redis->setex($cacheKey, 36000, json_encode($roles));

        return $roles;
    }

    public function clearRoleCache()
    {
        $redis = Redis::connection('default');
        $redis->del("user_roles_{$this->id}");
    }

    public function hasRole($roleId)
    {
        $roles = $this->getCachedRoles();
        return in_array($roleId, $roles);
    }

    public function hasAnyRole(array $roleIds)
    {
        $roles = $this->getCachedRoles();
        return count(array_intersect($roles, $roleIds)) > 0;
    }

    public function isSuperAdmin()
    {
        return $this->hasRole(1);
    }

    public function isSupervisor()
    {
        return $this->hasRole(2);
    }

    public function isUploader()
    {
        return $this->hasRole(3);
    }

    public function isReviewer()
    {
        return $this->hasRole(4);
    }

    public function isOperator()
    {
        return $this->hasRole(5);
    }

    public function isProjectSupervisor()
    {
        return $this->hasRole(6);
    }

    public function isCoverer()
    {
        return $this->hasRole(7);
    }

    public function checkUserRole($roleIds)
    {
        return $this->hasAnyRole((array) $roleIds);
    }
}
