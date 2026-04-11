<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SubtitleLanguage extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'name',
        'status',
        'label'
    ];
    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];

    public const TITLE = '生成字幕语言';
    public const CRUD_ROUTE_PART = 'subtitleLanguages';

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

        return $query;
    }
}
