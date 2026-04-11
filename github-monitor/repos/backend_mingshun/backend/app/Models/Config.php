<?php

namespace App\Models;

use App\Trait\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class Config extends Model
{
    use Log;
    use HasFactory;
    protected $fillable = [
        'value',
        'key',
        'description'
    ];
    public $timestamps = false;
    public const TITLE = '设置';
    public const CRUD_ROUTE_PART = 'configs';

    public static function getCachedConfig($key)
    {
        $redis = Redis::connection('default');
        $cacheKey = "config_{$key}";

        if ($redis->exists($cacheKey)) {
            return $redis->get($cacheKey);
        }

        $config = static::where('key', $key)->first();

        // Prevent error if key not found
        if (!$config) {
            return null;
        }

        $value = $config->value;

        // Cache for 10 hours (36000 seconds)
        $redis->setex($cacheKey, 36000, $value);

        return $value;
    }

    public static function clearConfigCache($key)
    {
        $redis = Redis::connection('default');
        $redis->del("config_{$key}");
    }
}
