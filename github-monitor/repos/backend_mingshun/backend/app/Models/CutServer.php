<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Trait\Log;

class CutServer extends Model
{
    use Log;
    use HasFactory;

    protected $fillable = [
        'ip',
        'redis_port',
        'redis_db',
        'redis_password',
        'idc',
    ];
    public const TITLE = '切片资源服务器';
    public const CRUD_ROUTE_PART = 'cutServer';

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
        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->id);
            });
        }

        return $query;
    }
}
