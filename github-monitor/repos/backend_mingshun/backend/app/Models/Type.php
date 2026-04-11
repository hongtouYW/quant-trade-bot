<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Type extends Model
{
    use Log;
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'name',
        'status',
        'assigned_order'
    ];
    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];
    public const IMPORT = [
        'name'
    ];
    public const TITLE = '分类';
    public const CRUD_ROUTE_PART = 'types';
    public const SELECT = 'name';

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

        if(Auth::user()->checkUserRole([3])){
            $query->active();
        }

        return $query;
    }

    protected static function booted()
    {
        static::deleting(function ($type) {
            $type->videos()->detach();
        });
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class,'video_types');
    }
}
