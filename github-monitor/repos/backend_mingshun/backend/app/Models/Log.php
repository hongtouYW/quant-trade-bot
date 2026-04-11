<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'user',
        'data',
        'model',
        'ip',
        'target_id'
    ];

    public const TYPE = [
        '1' => '创建',
        '2' => '编辑',
        '3' => '删除',
    ];

    public const MODEL = [
        "Author" => "作者",
        "Config" => "设置",
        "Ftp" => "FTP",
        "Project" => "项目",
        "Server" => "服务器",
        "Tag" => "标签",
        "Type" => "分类",
        "User" => "用户",
        "Video" => "视频",
        "VideoChoose" => "视频预选区",
        "Post" => "帖子",
        "PostVideo" => "帖子视频",
        "PostChoose" => "帖子预选区",
        "ProjectTypes" => '项目主题',
        "ProjectRules" => '项目规则',
        "TokenLogs" => '徽章日志',
        "Photo" => "图片水印",
        "PhotoProjectRule" => "图片规则",
    ];

    public const ROUTE = [
        "Author" => Author::CRUD_ROUTE_PART,
        "Config" => Config::CRUD_ROUTE_PART,
        "Ftp" => Ftp::CRUD_ROUTE_PART,
        "Project" => Project::CRUD_ROUTE_PART,
        "Server" => Server::CRUD_ROUTE_PART,
        "Tag" => Tag::CRUD_ROUTE_PART,
        "Type" => Type::CRUD_ROUTE_PART,
        "User" => User::CRUD_ROUTE_PART,
        "Video" => Video::CRUD_ROUTE_PART,
        "VideoChoose" => VideoChoose::CRUD_ROUTE_PART,
        "Post" => Post::CRUD_ROUTE_PART,
        "PostVideo" => '',
        "PostChoose" => PostChoose::CRUD_ROUTE_PART,
        "ProjectTypes" => ProjectTypes::CRUD_ROUTE_PART,
        "ProjectRules" => ProjectRules::CRUD_ROUTE_PART,
        "TokenLogs" => '',
    ];

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'user');
    }

    public function scopeSearch($query, Request $request)
    {
        if ($request->model !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('model', $request->model);
            });
        }

        if ($request->target_id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('target_id', $request->target_id);
            });
        }

        if ($request->user !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('user', $request->user);
            });
        }

        if ($request->created_at_from !== null || $request->created_at_to !== null) {
            if($request->created_at_from !== null && $request->created_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('created_at', '>=' ,$request->created_at_from. " 00:00:00")
                    ->where('created_at', '<' ,$request->created_at_to . " 23:59:59");
                });
            }else if($request->created_at_from !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('created_at', '>=' ,$request->created_at_from. " 00:00:00");
                });
            }else if($request->created_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('created_at', '<=' ,$request->created_at_to. " 23:59:59");
                });
            }
           
        }

        return $query;
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }
}
