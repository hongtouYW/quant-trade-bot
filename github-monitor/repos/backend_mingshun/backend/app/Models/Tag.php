<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Tag extends Model
{
    use Log;
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'name',
        'status'
    ];
    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];
    public const IMPORT = [
        'name'
    ];
    public const TITLE = '标签';
    public const CRUD_ROUTE_PART = 'tags';
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

        return $query;
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class,'video_tags');
    }

    protected static function booted()
    {
        static::deleting(function ($tag) {
            $tag->videos()->detach();
        });
    }

}
