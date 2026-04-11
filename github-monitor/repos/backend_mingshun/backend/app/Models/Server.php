<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Illuminate\Http\Request;

class Server extends Model
{
    use Log;
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'play_domain',
        'lan_domain',
        'ip',
        'path',
        'created_by',
        'status',
        'post_recommended',
        'idc',
    ];
    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',
    ];
    public const TITLE = '资源服务器';
    public const CRUD_ROUTE_PART = 'servers';
    public const SELECT = 'CONCAT(name," (",ip,")")';

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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ftps()
    {
        return $this->hasMany(Ftp::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }
}
