<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Trait\Log;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhotoProjectRule extends Model
{
    use HasFactory;
    use Log;

    public const STATUS = [
        '0' => '关闭',
        '1' => '开启',    
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
        'project_id',
        'status',
        'font_enable',
        'font_position',
        'font_borderSpace',
        'font_fontName',
        'font_fontSize',
        'font_fontColor',
        'font_lineSpace',
        'font_text1',
        'font_text2',
        'font_text3',
        'icon_enable',
        'icon_path',
        'icon_position',
        'icon_padding',
        'icon_scale',
    ];

    public const TITLE = '图片规则';
    public const CRUD_ROUTE_PART = 'pphotorules';

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
