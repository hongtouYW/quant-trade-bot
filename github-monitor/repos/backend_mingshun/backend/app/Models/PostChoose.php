<?php

namespace App\Models;

use App\Http\Helper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use App\Trait\Log;
use Illuminate\Http\Request;

class PostChoose extends Model
{
    use Log;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'created_by',
        'created_at',
        'post_id',
        'status'
    ];

    public const STATUS = [
        '1' => '已预选',
        '2' => '切片发送至hugo中',
        '3' => '发送失败',
        '4' => '已发送',
        '5' => '成功',
        '6' => '重新审核',
    ];
    public const TITLE = '帖子预选区';
    public const CRUD_ROUTE_PART = 'postsChoose';
    public const CUT_BTN = 'cut-status-model-btn';

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function scopeSearch($query, Request $request)
    {
        if(Auth::user()->role_id != 1){
            $project = Auth::user()->projects->first();
            if($project){
                $query->where(function ($q) use ($project) {
                    $q->where('project_id', $project->id);
                });
            }
        }else{
            if ($request->project_id !== null) {
                $query->where(function ($q) use ($request) {
                    $q->where('project_id', $request->project_id);
                });
            }
        }

        if($request->history){
            $query->whereNotIn('status', [1]);
        }else{
            $query->where('status', 1);
        }
        if ($request->status !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('post_chooses.status', $request->status);
            });
        }

        if ($request->created_by !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->created_by);
            });
        }

        if ($request->tag !== null) {
            $query->whereHas('post.tags', function ($q) use ($request) {
                $q->where("tags.id", $request->tag);
            });
        }

        if ($request->type !== null) {
            $query->whereHas('post.types', function ($q) use ($request) {
                $q->where("types.id", $request->type);
            });
        }

        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('post_chooses.id', $request->id);
            });
        }

        return $query;
    }

    public static function getTableRowAction($row, $crudRoutePart, $isButton = 1)
    {
        $html = "";
        if ($row->status == 1) {
            $html .= view('widget.changeStatusButtons', [
                'confirmWord' => "确定不预选吗？",
                'crudRoutePart' => $crudRoutePart . '.changeStatus',
                'id' => $row->id,
                'title' => '不预选',
                'class' => 'btn-warning',
                'value' => 0,
                'isButton' => $isButton,
                'modalBtnClass' => '',
                'extra' => 0,
            ]);
            if(Auth::user()->checkUserRole([1, 6])){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定切片吗？",
                    'crudRoutePart' => $crudRoutePart . '.cutStatus',
                    'id' => $row->id,
                    'title' => '切片',
                    'class' => 'btn-info',
                    'value' => 2,
                    'isButton' => $isButton,
                    'modalBtnClass' => PostChoose::CUT_BTN,
                    'extra' => 0,
                ]);
            }
        }

        if ($html != "" && $isButton) {
            $html .= '<hr>';
        }

        if (!Auth::user()->checkUserRole([3])) {
            $html .= view('widget.viewActionButtons', [
                'crudRoutePart' => 'posts',
                'id' => $row->post->id,
                'isButton' => $isButton
            ]);
        }

        return $html;
    }

    // @TODO change to newest hugo step
    public static function sendVideo($title, $path, $servers, $id, $total, $key, $request){
       
        return false;
    }

    public static function cutStatus($request, $id){
        $postChoose = PostChoose::findOrFail($id);
        $post = $postChoose->post;
        $total = count($post->videos);
        $success_post_video_id = [];
        $failed_post_video_id = [];
        foreach($post->videos as $key=>$video){
            $path = str_replace("/public","",$video->path);
            $isSend = self::sendVideo($post->title, $path, $post->servers, $postChoose->id, $total, $key + 1, $request);
            if($isSend){
                $success_post_video_id[] = $video->id;
            }else{
                $failed_post_video_id[] = $video->id;
            }
        }
        if (count($success_post_video_id) == $total) {
            $postChoose->status = 2;
        } else {
            $postChoose->status = 3;
            $postChoose->return_msg = implode(",",$failed_post_video_id) . '发送失败';
        }
        $postChoose->save();
    }
}
