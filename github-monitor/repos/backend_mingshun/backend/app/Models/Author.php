<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;


class Author extends Model
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
    public const TITLE = '作者';
    public const CRUD_ROUTE_PART = 'authors';
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
        return $this->hasMany(Video::class);
    }
}
