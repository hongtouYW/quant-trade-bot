<?php

namespace App\Models;

use App\Trait\Log;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectRules extends Model
{
    use HasFactory;
    use Log;

    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
    ];

    public const RESOLUTION = [
        '0' => '480',
        '1' => '720',    
        '2' => '1080', 
        '3' => '2160', 
    ];

    public const LOGO = [
        '0' => '左上',
        '1' => '右上',    
        '2' => '左下', 
        '3' => '右下', 
    ];

    public const FONT = [
        '0' => '上',
        '1' => '中',    
        '2' => '下', 
    ];

    public const PICTUREFONTNAME = [
        'font1' => '字体1',
        'font2' => '字体2',    
        'font3' => '字体3', 
        'font4' => '字体4', 
    ];

    protected $fillable = [
        'name',
        'head_enable',
        'head_video',
        'logo_enable',
        'logo_image',
        'logo_position',
        'logo_padding',
        'logo_scale',
        'font_enable',
        'font_text',
        'font_color',
        'font_size',
        'font_position',
        'font_interval',
        'font_scroll',
        'font_border',
        'font_shadow',
        'font_space',
        'ad_enable',
        'ad_image',
        'ad_start',
        'm3u8_enable',
        'm3u8_encrypt',
        'm3u8_interval',
        'm3u8_bitrate',
        'm3u8_fps',
        'preview_enable',
        'preview_interval',
        'preview_skip',
        'webp_enable',
        'webp_start',
        'webp_interval',
        'webp_count',
        'webp_length',
        'skip_enable',
        'skip_head',
        'skip_back',
        'resolution_option',
        'callback_url',
        'project_id',
        'status',
        'generate_subtitle',
        'subtitle_languages'
    ];

    public const TITLE = '规则';
    public const CRUD_ROUTE_PART = 'prules';

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
}
