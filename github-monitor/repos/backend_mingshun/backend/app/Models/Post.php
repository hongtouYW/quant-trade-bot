<?php

namespace App\Models;

use App\Http\Helper;
use App\Trait\LogWithManyToMany;
use Carbon\Carbon;
use CURLFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;

class Post extends Model
{
    use LogWithManyToMany;

    public const STATUS = [
        '1' => '未审核',
        '2' => '审核一次',
        '3' => '上架',
        '4' => '审核不通过',
    ];

    protected $fillable = [
        'uid',
        'title',
        'description',
        'uploader',
        'status',
        'source',
        'status',
        'created_at',
        'server_id'
    ];

    public const BELONGTOMANY = [
        'types', 'tags'
    ];
    
    public const TITLE = '帖子';
    public const CRUD_ROUTE_PART = 'posts';
    public const REJECT_BTN = 'reject-status-model-btn';

    public function postTags()
    {
        return $this->hasMany(PostTag::class);
    }

    public function postTypes()
    {
        return $this->hasMany(PostType::class);
    }

    public function images()
    {
        return $this->hasMany(PostImage::class);
    }

    public function videos()
    {
        return $this->hasMany(PostVideo::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    public function types()
    {
        return $this->belongsToMany(Type::class, 'post_types');
    }

    public function firstApprovedByUser()
    {
        return $this->belongsTo(User::class, 'first_approved_by');
    }

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

    protected function firstApprovedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function secondApprovedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    public function uploaderUser()
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function chooseUser()
    {
        return $this->belongsToMany(User::class, 'post_chooses', 'post_id', 'created_by');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'post_chooses', 'post_id', 'project_id');
    }

    public function activeProjects()
    {
        return $this->belongsToMany(Project::class, 'post_chooses', 'post_id', 'project_id')->wherePivotIn('status', [2, 3, 4, 5]);
    }

    public function chooseProject()
    {
        return $this->hasMany(PostChoose::class, 'post_id');
    }

    public function servers()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function scopeSearch($query, Request $request)
    {
        if ($request->fail) {
            $query->where('status', 4);
        } else {
            if (Auth::user()->checkUserRole([5, 6])) {
                $query->where('status', 3);
            }
            if ($request->status !== null) {
                $query->where(function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }
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

        if ($request->first_approved_at_from !== null || $request->first_approved_at_to !== null) {
            if($request->first_approved_at_from !== null && $request->first_approved_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('first_approved_at', '>=' ,$request->first_approved_at_from. " 00:00:00")
                    ->where('first_approved_at', '<' ,$request->first_approved_at_to . " 23:59:59");
                });
            }else if($request->first_approved_at_from !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('first_approved_at', '>=' ,$request->first_approved_at_from. " 00:00:00");
                });
            }else if($request->first_approved_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('first_approved_at', '<=' ,$request->first_approved_at_to. " 23:59:59");
                });
            }
           
        }

        if ($request->uploader !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('uploader', $request->uploader);
            });
        }

        if ($request->approved_by !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('first_approved_by', $request->approved_by);
            });
        }

        if ($request->tag !== null) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where("id", $request->tag);
            });
        }

        if ($request->type !== null) {
            $query->whereHas('types', function ($q) use ($request) {
                $q->where("id", $request->type);
            });
        }

        if (Auth::user()->isUploader()) {
            $query->where('uploader', Auth::user()->id);
        }

        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('id', $request->id);
            });
        }

        return $query;
    }

    public static function getTableRowAction($row, $crudRoutePart, $isButton = 1)
    {
        $html = "";
        if (Auth::user()->checkUserRole([1, 2, 4])) {
            if ($row->status == 1) {
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => '确定通过吗？',
                    'crudRoutePart' => $crudRoutePart . '.changeStatus',
                    'id' => $row->id,
                    'title' => "通过<i class='bx bx-check'></i>",
                    'class' => 'btn-success same-btn-width',
                    'value' => 3,
                    'isButton' => $isButton,
                    'modalBtnClass' => '',
                    'extra' => 0,
                ]);
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => '确定不通过吗？',
                    'crudRoutePart' => $crudRoutePart . '.changeStatusModal',
                    'id' => $row->id,
                    'title' => "不通过<i class='bx bx-x' ></i>",
                    'class' => 'btn-warning same-btn-width',
                    'value' => 4,
                    'isButton' => $isButton,
                    'modalBtnClass' => Post::REJECT_BTN,
                    'extra' => 0,
                ]);
            }
        }
        if (Auth::user()->checkUserRole([1, 2]) && ($row->status == 3 || $row->status == 4)) {
            $confirmWord = "确定重新审核吗？";
                $project_name = implode(",", $row->activeProjects->pluck('name')->toArray());
                if ($project_name) {
                    $confirmWord = '视频已被 '.$project_name .' 切片/同步。是否继续重新审核?';
                }
            $html .= view('widget.changeStatusButtons', [
                'confirmWord' =>  $confirmWord,
                'crudRoutePart' => $crudRoutePart . '.changeStatus',
                'id' => $row->id,
                'title' => '重新审核',
                'class' => 'reset-checking-btn',
                'value' => 1,
                'isButton' => $isButton,
                'modalBtnClass' => '',
                'extra' => 0,
            ]);
        }
        if (Auth::user()->checkUserRole([1, 5, 6])) {
            $select_flag = true;
            if (!($row->chooseProject->isEmpty())) {
                $user = Auth::user();
                $project = $user->projects->first();
                if ($project) {
                    if (in_array($project->id, $row->chooseProject->pluck('project_id')->toArray())) {
                        $select_flag = false;
                    }
                }
            }
            if ($row->status == 3) {
                if ($select_flag) {
                    $html .= view('widget.changeStatusButtons', [
                        'confirmWord' => "",
                        'crudRoutePart' => $crudRoutePart . '.changeStatus',
                        'id' => $row->id,
                        'title' => '预选',
                        'class' => 'btn-success',
                        'value' => 5,
                        'isButton' => $isButton,
                        'modalBtnClass' => '',
                        'extra' => 0,
                    ]);
                } else {
                    if ($isButton) {
                        $html .= '<button class="btn btn-sm btn-disable waves-effect waves-light">已预选</button>';
                    } else {
                        $html .= " <input class='input-disable change-status-model-grid-button' value='已预选'>";
                    }
                }
            }
        }

        if ($html != "" && $isButton) {
            $html .= '<hr>';
        }

        if (!Auth::user()->checkUserRole([3])) {
            $html .= view('widget.viewActionButtons', [
                'crudRoutePart' => $crudRoutePart,
                'id' => $row->id,
                'isButton' => $isButton
            ]);
        }

        $html .= view('widget.actionButtons', [
            'crudRoutePart' => $crudRoutePart,
            'row' => $row,
            'edit' => Post::getEditVideoStatus($row->status),
            'delete' => Post::getDeleteVideoStatus($row->status),
            'isButton' => $isButton
        ]);

        return $html;
    }


    public static function getEditVideoStatus($status)
    {
        if (Auth::user()->isSuperAdmin()) {
            return 1;
        } else if (Auth::user()->checkUserRole([2, 3, 4])) {
            if ($status == 1 || $status == 2 || $status == 4) {
                return 1;
            }
        }
        return 0;
    }

    public static function getDeleteVideoStatus($status)
    {
        if (Auth::user()->isSuperAdmin()) {
            return 1;
        } else if (Auth::user()->checkUserRole([2, 3])) {
            if ($status == 1) {
                return 1;
            }
        }
        return 0;
    }

    public static function changeStatus($post, $status)
    {
        switch ($status) {
            case 1:
                foreach($post->chooseProject as $postChoose){
                    $postChoose->status = 6;
                    $postChoose->save();
                }
                $post->first_approved_by = null;
                $post->first_approved_at = null;
                $post->reason = "";
                $post->status = $status;
                break; 
            case 3:
                $post->first_approved_by = Auth::user()->id;
                $post->first_approved_at = now();
                $post->status = $status;
                break;
            case 4:
                $post->first_approved_by = Auth::user()->id;
                $post->first_approved_at = now();
                $post->status = $status;
                break;
            case 5:
                $user = Auth::user();
                $project = $user->projects->first();
                if (!$project) {
                    throw new \Exception('用户没相关项目，无法预选');
                }
                $postChoose = PostChoose::where('post_id', $post->id)->where('project_id', $project->id)->first();
                if ($postChoose) {
                    if($postChoose->status == 6){
                        $postChoose->status == 1;
                        $postChoose->save();
                    }else{
                        throw new \Exception('帖子已预选');
                    }
                }
                PostChoose::create([
                    'project_id' => $project->id,
                    'created_by' => $user->id,
                    'created_at' => now(),
                    'post_id' => $post->id,
                    'status' => 1
                ]);
                break;
        }
        return $post;
    }

    // 1: 图片
    // 2: 视频
    // 3: 文件
    // 4: 文案.html
    public static function sendFile($request, $type, $file, $uid){
        if($request->id == 0){
            $server = Server::where('post_recommended', 1)->first();
        }else{
            $post = Post::find($request->id);
            $server = $post->servers;
        }
        $response = Helper::sendResourceRequest(
            $server->domain . "/api/upload/post/file ",
            array('documentID' => $uid, 'fileType' => $type, 'file'=>$file),
            array('Content-Type: multipart/form-data'),
            'Send Post File'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            if ($response->data ?? '') {
                return $response->data;
            }
        }
    }

    public static function deleteFile($id, $uid, $path){
        if($id == 0){
            $server = Server::where('post_recommended', 1)->first();
        }else{
            $post = Post::find($id);
            $server = $post->servers;
        }
        $parsedUrl = parse_url($path);
        $path = $parsedUrl['path'];
        Helper::sendResourceRequest(
            $server->domain . "/api/delete/post/file ",
            json_encode(array('documentID' => $uid, 'path' => $path)),
            array('Content-Type: application/json'),
            'Delete Post File'
        );
    }
}
