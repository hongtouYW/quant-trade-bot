<?php

namespace App\Models;

use App\Http\Helper;
use App\Trait\LogWithManyToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log as FacadesLog;

class Video extends Model
{
    use LogWithManyToMany;
    use HasFactory;

    public const STATUS = [
        '1' => '未审核',
        '2' => '审核一次',
        '3' => '上架',
        '4' => '审核不通过',
        '5' => '直接切片'
    ];

    public const COVERSTATUS = [
        '1' => '未更换',
        '2' => '已更换'
    ]; 

    public const COVER_FROM = [
        '1' => '上传手',
        '2' => 'Hugo',
    ];

    protected $fillable = [
        'uid',
        'title',
        'description',
        'path',
        'uploader',
        'status',
        'cover_photo',
        'cover_from',
        'server_id',
        'code',
        'size',
        'resolution',
        'resolution_tag',
        'others',
        'author_id',
        'code_others',
        'md5',
        'pre_cut_seconds',
        'post_cut_seconds',
        'source',
        'assigned_to',
        'assigned_at',
        'rereviewer_by',
        'rereviewer_at',
        'cover_status',
        'cover_assigned_to',
        'cover_assigned_at',
        'cover_changed_at',
        'cover_vertical',
        'remark',
        'project_id',
        'website'
    ];

    public const ISREREVIEW = [
        '0' => '否',
        '1' => '是',      
    ];

    public const BELONGTOMANY = [
        'types', 'tags'
    ];

    public const SHOWCODEOTHERS = 8;

    public const RESOLUTION_FILTER = [
        '1' => "4k",
        '2' => "高清",
        '3' => "标准",
        '4' => "低清"
    ];

    public const RESOLUTION_FILTER_RANGE = [
        '1' => "2160",
        '2' => "1080",
        '3' => "480",
        '4' => "144"
    ];

    public const REJECT_BTN = 'reject-status-model-btn';
    public const REREVIEW_BTN = 'reject-status-model-btn';
    public const TITLE = '视频';
    public const CRUD_ROUTE_PART = 'videos';

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

    protected function assignedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function rereviewerAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function size(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Helper::formatBytes($value) : '',
        );
    }

    public function firstApprovedByUser()
    {
        return $this->belongsTo(User::class, 'first_approved_by');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedCoverUser()
    {
        return $this->belongsTo(User::class, 'cover_assigned_to');
    }

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function videoTags()
    {
        return $this->hasMany(VideoTag::class);
    }

    public function videoTypes()
    {
        return $this->hasMany(VideoType::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'video_tags');
    }

    public function types()
    {
        return $this->belongsToMany(Type::class, 'video_types');
    }

    public function servers()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function authors()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function uploaderUser()
    {
        return $this->belongsTo(User::class, 'uploader');
    }

    public function reReviewUser()
    {
        return $this->belongsTo(User::class, 'rereviewer_by');
    }

    public function chooseUser()
    {
        return $this->belongsToMany(User::class, 'video_chooses', 'video_id', 'created_by');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'video_chooses', 'video_id', 'project_id');
    }

    public function activeProjects()
    {
        return $this->belongsToMany(Project::class, 'video_chooses', 'video_id', 'project_id')->wherePivotIn('status', [2, 3, 4, 5]);
    }

    public function chooseProject()
    {
        return $this->hasMany(VideoChoose::class, 'video_id');
    }

    public function projectTypes()
    {
        return $this->belongsToMany(
            ProjectTypes::class,
            'video_choose_project_types',
            'video_id',  
            'project_types_id'  
        );
    }

    public function scopeSearch($query, Request $request)
    {
        if ($request->success && !Auth::user()->checkUserRole([7])) {
            $query->where('status', 3);
        }elseif ($request->reReview && !Auth::user()->checkUserRole([7])) {
            $query->where('status', 1);
            $query->whereNotNull('rereviewer_by');
        }else{
            if (Auth::user()->checkUserRole([5, 6])) {
                $query->where('status', 3);
                $project = Auth::user()->projects->first();
                if($project){
                    if(!$project->enable_4k){
                        $query->where('resolution_tag', '!=' ,1);
                    }
                    if($request->resolution == 1){
                        unset($request->resolution);
                    }
                    $query->doesntHave('chooseProject');
                }
            }elseif (Auth::user()->checkUserRole([4])) {
                $query->where('status', 1);
            }else{
                if ($request->status !== null) {
                    $query->where(function ($q) use ($request) {
                        $q->where('status', $request->status);
                    });
                }
            }
            
        }

        if(Auth::user()->checkUserRole([4])){
            $query->where('assigned_to', Auth::user()->id);
            if (!$request->reReview && !$request->success) {
                $query->whereNull('rereviewer_by');
            }
        }else{
            if ($request->assigned_to !== null) {
                if($request->assigned_to == 0){
                    $query->whereNull('assigned_to');
                }else{
                    $query->where('assigned_to', $request->assigned_to);
                }
            }
        }


        if(Auth::user()->checkUserRole([7])){
            $query->where('cover_assigned_to', Auth::user()->id);
        }else{
            if ($request->cover_assigned_to !== null) {
                if($request->cover_assigned_to == 0){
                    $query->whereNull('cover_assigned_to');
                }else{
                    $query->where('cover_assigned_to', $request->cover_assigned_to);
                }
            }
        }

        if(Auth::user()->checkUserRole([7])){
            if ($request->success) {
                $query->where('cover_status', 2);
            }elseif ($request->reReview) {
                $query->where('cover_status', 3);
            }else{
                $query->where('cover_status', 1);
            }
        }else{
            if ($request->cover_status !== null) {
                $query->where('cover_status', $request->cover_status);
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

        if ($request->website !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('website', $request->website);
            });
        }

        if ($request->project_types !== null) {
            $query->whereHas('projectTypes', function ($q) use ($request) {
                $q->where("project_types.id", $request->project_types);
            });
        }

        if ($request->approved_by !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('first_approved_by', $request->approved_by);
            });
        }

        if ($request->tag !== null) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where("tags.id", $request->tag);
            });
        }

        if ($request->type !== null) {
            $query->whereHas('types', function ($q) use ($request) {
                $q->where("types.id", $request->type);
            });
        }

        if ($request->author !== null) {
            $query->whereHas('author', function ($q) use ($request) {
                $q->where("authors.id", $request->author);
            });
        }

        if ($request->resolution) {
            $query->where('resolution_tag', $request->resolution);
        }

        if (Auth::user()->isUploader()) {
            $query->where('uploader', Auth::user()->id);
        }

        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('videos.id', $request->id);
            });
        }

        if ($request->source !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('source', 'like', '%'. $request->source . '%');
            });
        }

        if (Auth::user()->checkUserRole([5, 6])) {
            $query->orderBy('first_approved_at', 'desc');
        }
        
        return $query;
    }

    public static function checkValid($path, $image = 0, $rule = 0)
    {
        $file_info = pathinfo($path);
        if($file_info['extension'] ?? ''){
            if($file_info['extension'] != strtolower($file_info['extension'])){
                throw new Exception("封面图/视频格式不能大写");
            }
        }else{
            throw new Exception("缺少封面图/视频格式");
        }
        if($image){
            $imageExtension = ['jpg','jpeg','png','webp'];
            if(!in_array($file_info['extension'], $imageExtension)){
                throw new Exception("封面图格式错误,只接受" . implode(',', $imageExtension));
            }
        }
        if($rule == 1){
            $checkPath = "https://ftp.minggogogo.com/api/check/rule/image";
            $full_path = $path;
            $logString = 'Check Rule Image';
        }else{
            $checkPath = "https://ftp.minggogogo.com/api/check/file";
            $full_path = '/home/public'.$path;
            $logString = 'Check File Valid';
        }
        list($response, $ip, $code) = Helper::sendResourceIpCodeRequest(
            $checkPath,
            json_encode(array('path' => $full_path)),
            array('Content-Type: application/json'),
            $logString
        );
        if($code == 504){
            return '系统错误,请稍后再重试';
        }
        $temp_response = json_decode($response);
        if($temp_response ?? ''){
            if (($temp_response->code ?? 0) != 200) {
                $errorMessage = '服务器不正确';
                if($temp_response->msg ?? ''){
                    if (strpos($temp_response->msg, 'only accept file check') !== false) {
                        $errorMessage = '没有extension';
                    } elseif (strpos($temp_response->msg, 'file not allow to check') !== false) {
                        $errorMessage = '不在共享文件夹';
                    } elseif (strpos($temp_response->msg, 'file not found') !== false) {
                        $errorMessage = '不存在/地址不正确';
                    } elseif (strpos($temp_response->msg, 'GetVideoDetail') !== false) {
                        $errorMessage = '有问题';
                    } elseif (strpos($temp_response->msg, 'require body data') !== false) {
                        $errorMessage = '没有API body';
                    } elseif (strpos($temp_response->msg, 'video too small') !== false) {
                        $errorMessage = '太小';
                    } elseif (strpos($temp_response->msg, 'filename and image type not suite') !== false) {
                        $errorMessage = '图片格式与文件扩展名不匹配';
                    } elseif (strpos($temp_response->msg, 'this is not a image file') !== false) {
                        $errorMessage = '该文件不是一个图片文件';
                    } elseif (strpos($temp_response->msg, 'broken file') !== false) {
                        $errorMessage = '损坏的文件';
                    } 
                }
                $temp_response->errorMessage = $errorMessage;
                return $temp_response;
            }else{
                if($rule !== 1){
                    if (isset($temp_response->data->path)) {
                        $temp_response->data->path = str_replace('/home/public',"",$temp_response->data->path);
                    }
                }
                return $temp_response;
            }
        }else{
            return $response;
        }
    }

    public static function sendCover($server_id, $video_path, $file, $id, $extension = 'png')
    {
        return self::sendCoverBase($server_id, $video_path, $file, $id, '/api/update/cover',$extension);
    }

    public static function sendCoverVertical($server_id, $video_path, $file, $id, $extension = 'png')
    {
        return self::sendCoverBase($server_id, $video_path, $file, $id, '/api/upload/cover',$extension, 'vertical');
    }

    public static function sendCoverBase($server_id, $video_path, $file, $id, $route,$extension, $pathExtra = '')
    {
        if ($video_path) {
            $cover_path = pathinfo($video_path, PATHINFO_DIRNAME) . "/cover" . $pathExtra. $id  . "_" . time() . "." .$extension;
        } else {
            $cover_path = "/cover" . $pathExtra . $id . "_" . time() . "." . $extension;
        }
        $server = Server::findOrFail($server_id);
        $response = Helper::sendResourceRequest(
            $server->lan_domain . $route,
            array('path' => $cover_path, 'cover' => $file),
            array('Content-Type: multipart/form-data'),
            'Send Video Cover '. $id
        );
        if(!$response){
            $response = Helper::sendResourceRequest(
                $server->domain . $route,
                array('path' => $cover_path, 'cover' => $file),
                array('Content-Type: multipart/form-data'),
                'Send Video Cover '. $id
            );
        }
        $response = json_decode($response);
        $errorMessage = '上传失败';
        if (($response->code ?? 0) == 200) {
            if ($response->data ?? '') {
                return array(true,str_replace($server->path, '', $response->data));
            }
        }else{
            if($response->msg ?? ''){
                if (strpos($response->msg, 'only accept file check') !== false) {
                    $errorMessage = '没有extension';
                } elseif (strpos($response->msg, 'file not allow to check') !== false) {
                    $errorMessage = '不在共享文件夹';
                } elseif (strpos($response->msg, 'file not found') !== false) {
                    $errorMessage = '不存在/地址不正确';
                } elseif (strpos($response->msg, 'GetVideoDetail') !== false) {
                    $errorMessage = '有问题';
                } elseif (strpos($response->msg, 'require body data') !== false) {
                    $errorMessage = '没有API body';
                } elseif (strpos($response->msg, 'video too small') !== false) {
                    $errorMessage = '太小';
                } elseif (strpos($response->msg, 'filename and image type not suite') !== false) {
                    $errorMessage = '图片格式与文件扩展名不匹配';
                } elseif (strpos($response->msg, 'this is not a image file') !== false) {
                    $errorMessage = '该文件不是一个图片文件';
                } elseif (strpos($response->msg, 'broken file') !== false) {
                    $errorMessage = '损坏的文件';
                } elseif (strpos($response->msg, 'cover size cannot bigger than 5MB') !== false) {
                    $errorMessage = '图片大小不能多过5MB';
                } elseif (strpos($response->msg, 'image file not support') !== false) {
                    $errorMessage = '不支持此图片格式';
                } 
            }
        }
        return array(false,$errorMessage);
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
                    'modalBtnClass' => Video::REJECT_BTN,
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
                'crudRoutePart' => $crudRoutePart . '.changeStatusModal',
                'id' => $row->id,
                'title' => '重新审核',
                'class' => 'reset-checking-btn',
                'value' => 1,
                'isButton' => $isButton,
                'modalBtnClass' => Video::REREVIEW_BTN,
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
                        'class' => 'btn-success bottom-margin-10',
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
        // $types = Config::getCachedConfig('cover_assigned_type');
        // if($types){
        //     $types = explode(",",$types);
        // }else{
        //     $types = [];
        // }
        // $intersect = array_intersect($types, $row->types?->pluck('id')?->toArray());
        if (Auth::user()->checkUserRole([1]) && $row->cover_status == 1 && $row->status == 3) {
            $html .= view('widget.changeStatusButtons', [
                'confirmWord' => "",
                'crudRoutePart' => $crudRoutePart . '.changeStatus',
                'id' => $row->id,
                'title' => '封面图完成',
                'class' => 'btn-blue bottom-margin-10',
                'value' => 999,
                'isButton' => $isButton,
                'modalBtnClass' => '',
                'extra' => 0,
            ]);
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
            'edit' => Video::getEditVideoStatus($row->status, $row->cover_status),
            'delete' => Video::getDeleteVideoStatus($row->status),
            'isButton' => $isButton
        ]);

        return $html;
    }

    public static function getEditVideoStatus($status, $cover_status)
    {
        if (Auth::user()->checkUserRole([1, 2])) {
            return 1;
        } else if (Auth::user()->checkUserRole([3, 4])) {
            if ($status == 1 || $status == 2 || $status == 4) {
                return 1;
            }
        } else if (Auth::user()->checkUserRole([7])) {
            if ($cover_status == 1) {
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

    public static function changeStatus($video, $status)
    {
        switch ($status) {
            case 1:
                foreach($video->chooseProject as $videoChoose){
                    $videoChoose->status = 6;
                    $videoChoose->save();
                    VideoChoose::processSaveLog([], $videoChoose, 2, []);
                }
                $video->first_approved_by = null;
                $video->first_approved_at = null;
                $video->rereviewer_by = Auth::user()->id;
                $video->rereviewer_at = now();
                $video->reason = "";
                $video->status = $status;
                $video->save();
                break;
            case 3:
                $video->first_approved_by = Auth::user()->id;
                $video->first_approved_at = now();
                $video->status = $status;
                $video->save();
                break;
            case 4:
                foreach($video->chooseProject as $videoChoose){
                    $videoChoose->status = 6;
                    $videoChoose->save();
                    VideoChoose::processSaveLog([], $videoChoose, 2, []);
                }
                $video->first_approved_by = Auth::user()->id ?? 0;
                $video->first_approved_at = now();
                $video->status = $status;
                $video->cover_status = 1;
                $video->cover_changed_at = null;
                $video->cover_assigned_to = null;
                $video->cover_assigned_at = null;
                $video->save();
                break;
            case 5:
                $user = Auth::user();
                $project = $user->projects->first();
                if (!$project) {
                    throw new \Exception('用户没相关项目，无法预选');
                }
                $videoChoose = VideoChoose::where('video_id', $video->id)->where('project_id', $project->id)->first();
                if ($videoChoose) {
                    if($videoChoose->status == 6){
                        $videoChoose->status = 1;
                        $videoChoose->save();
                        VideoChoose::processSaveLog([], $videoChoose, 2, []);
                    }else{
                        throw new \Exception('视频已预选');
                    }
                }else{
                    $videoChoose = VideoChoose::create([
                        'project_id' => $project->id,
                        'created_by' => $user->id,
                        'created_at' => now(),
                        'video_id' => $video->id,
                        'status' => 1
                    ]);
                    VideoChoose::processSaveLog([], $videoChoose, 1, []);
                }
                break;
            case 999:
                $video->cover_status = 2;
                $video->cover_assigned_to = 1;
                $video->cover_assigned_at = now();
                $video->cover_changed_at = now();
                $video->save();
        }
        
        return $video;
    }


    public static function getResolutionTag($resolution)
    {
        $temp = explode("x", $resolution);
        if ($temp[1] ?? '') {
            $resolution_tag = 4;
            foreach (Video::RESOLUTION_FILTER_RANGE as $key => $range) {
                if ($temp[1] >= $range) {
                    $resolution_tag = $key;
                    break;
                }
            }
            return $resolution_tag;
        }
        return null;
    }

    public static function customImportBase($request, $returnId = 1){
        $type_id = 0;
        $video_type = $request->get('type','');
        if ($video_type) {
            $type = Type::firstOrCreate([
                'name' => $video_type,
            ], [
                'status' => 1,
            ]);
            $type_id = $type->id;
        }

        $others = [];
        $tag = [];
        $video_tag = $request->get('tag','');
        if (isset($video_tag)) {
            if(gettype($video_tag) == 'array'){
                $others = [
                    '标签' => json_encode($video_tag, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                ];
                foreach($video_tag as $tag_api){
                    $tag_id = Tag::firstOrCreate([
                        'name' => trim($tag_api),
                    ], [
                        'status' => 1,
                    ]);
                    $tag[] = $tag_id->id;
                }
            }else{
                if(trim($video_tag) != ""){
                    $others = [
                        '标签' => json_encode([$video_tag], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                    ];
                    $tag_id = Tag::firstOrCreate([
                        'name' => trim($video_tag),
                    ], [
                        'status' => 1,
                    ]);
                    $tag = [$tag_id->id];
                }else{
                    $tag = [];
                }
            }
        }
        $others_json = json_encode($others);
        $video = Video::videoApi($request, [$type_id], $tag, $request->get('source',''), 
            $others_json, $request->get('username',''));
        if($returnId){
            return $video->id;
        }else{
            return $video;
        }
    }

    public static function videoApi($request, $types, $tags, $source= '', $others = '', $username='' ,$update = 0){
        $video = $request->all();

        $randomString = bin2hex(random_bytes(4));
        $processId = getmypid();

        if(!isset($video['title'])){
            throw new Exception("缺少title参数");
        }
         if(!isset($video['video'])){
            throw new Exception("缺少video参数");
        }

        $videoDB = Video::where('path', $video['video'])->first();
        if($videoDB && !$update){
            throw new Exception("视频链接重复");
        }

        $videoDBTitle = Video::where('title', $video['title'])->first();
        if($videoDBTitle){
            throw new Exception("视频标题重复");
        }

        $md5 = '';
        if (isset($video['md5'])) {
            $md5 = $video['md5'];
        }
        
        $author_id = 0;
        if (isset($video['author'])) {
            $author = Author::firstOrCreate([
                'name' => trim(str_replace('&nbsp;','',$video['author'])),
            ], [
                'status' => 1,
            ]);
            $author_id = $author->id;
        }

        $response = Video::checkValid($video['video']);
        if (($response->code ?? 0) == 200) {
            if (isset($response->data)) {
                if (isset($response->data->size)) {
                    $size = $response->data->size;
                }
                if (isset($response->data->resolution)) {
                    $resolution = $response->data->resolution;
                    $resolution_tag = Video::getResolutionTag($resolution ?? "");
                }
                if (isset($response->data->md5)) {
                    $md5 = $response->data->md5;
                }
                if (isset($response->data->path)) {
                    $video['video'] = $response->data->path;
                }
            }
        }else{
            if($response->errorMessage ?? ''){
                if(gettype($response->errorMessage) == 'string'){
                    $error = $response->errorMessage;
                }else{
                    $error = json_encode($response->errorMessage);
                }   
            }else{
                if(gettype($response) == 'string'){
                    $error = $response;
                }else{
                    $error = json_encode($response);
                }
            }
            throw new Exception('视频' . $error);
        }
        $server_id = Video::baseCheckLanDomain('https://resources.minggogogo.com',$video['video'] ?? '',$video['image'] ?? '',$video['image_vertical'] ?? '');

        $subtitleContents = '';
        if($video['subtitle'] ?? ''){
            $file_info = pathinfo($video['subtitle']);
            if($file_info['extension'] ?? ''){
                if($file_info['extension'] != strtolower($file_info['extension'])){
                    throw new Exception("字幕格式不能大写");
                }
            }else{
                throw new Exception("缺少字幕格式");
            }
            if(!empty($extension)){
                if(!in_array($file_info['extension'], $extension)){
                    throw new Exception("字幕格式错误,只接受" . implode(',', ['.srt','.ass']));
                }
            }
            $server = Server::findOrFail($server_id);
            $subtitleResponse = Http::get($server->domain . "/public" . $video['subtitle']);
            if ($subtitleResponse->successful()) {
                $subtitleContents = $subtitleResponse->body();
            } else {
                throw new Exception('字幕错误');
            }
        }

        if($video['image'] ?? ''){
            $response = Video::checkValid($video['image'], 1);
            if (($response->code ?? 0) != 200) {
                if($response->errorMessage ?? ''){
                    if(gettype($response->errorMessage) == 'string'){
                        $error = $response->errorMessage;
                    }else{
                        $error = json_encode($response->errorMessage);
                    }   
                }else{
                    if(gettype($response) == 'string'){
                        $error = $response;
                    }else{
                        $error = json_encode($response);
                    }
                }
                throw new Exception('封面图' . $error);
            }else{
                if (isset($response->data->path)) {
                    $video['image'] = $response->data->path;
                }
            }
        }

        if($video['image_vertical'] ?? ''){
            $response = Video::checkValid($video['image_vertical'], 1);
            if (($response->code ?? 0) != 200) {
                if($response->errorMessage ?? ''){
                    if(gettype($response->errorMessage) == 'string'){
                        $error = $response->errorMessage;
                    }else{
                        $error = json_encode($response->errorMessage);
                    }   
                }else{
                    if(gettype($response) == 'string'){
                        $error = $response;
                    }else{
                        $error = json_encode($response);
                    }
                }
                throw new Exception('竖图' . $error);
            }else{
                if (isset($response->data->path)) {
                    $video['image_vertical'] = $response->data->path;
                }
            }
        }

        $status = 1;
        
        $directCut = false;
        if($username){
            $user = User::where('username', $username)->first();
            if(!$user){
                throw new Exception("用户名字不正确");
            }
            $uploader = $user->id;
            $projects = $user->projects->first();
            if($projects?->direct_cut){
                $directCut = true;
                $status = 5;
            }
            $projectId = $projects?->id ?? null;
        }else{
            $uploader = 0;
            $projectId = null;
        }

        if($directCut){
            if(!($video['rule'] ?? '')){
                throw new Exception("直切缺少rule参数");
            }
            $rules = ProjectRules::where('name', $video['rule'])->where('project_id',$projects->id)->first();
            if(!$rules){
                throw new Exception("rule错误/不存在");
            }
            $ruleId = $rules->id;

            $themeId = [];
            foreach(($video['theme'] ?? []) as $theme){
                $types = ProjectTypes::where('name', $theme)->where('project_id',$projects->id)->first();
                if(!$types){
                    throw new Exception("theme错误/不存在");
                }
                $themeId[] = $types->id;
            }
        }else{
            if($video['rule'] ?? ''){
                if(!$username){
                    throw new Exception("无用户名，无法直切,不需要rule参数");
                }
                throw new Exception("用户项目无法直切,不需要rule参数");
            }
            if(count($video['theme'] ?? []) > 0){
                if(!$username){
                    throw new Exception("无用户名，无法直切,不需要theme参数");
                }
                throw new Exception("用户项目无法直切,不需要theme参数");
            }
        }
        if(!$videoDB){
            $videoInsert = Video::create([
                'title' => str_replace('&nbsp;','',($video['title'] ?? "")),
                'description' => str_replace('&nbsp;','',($video['description'] ?? "")),
                'path' => $video['video'] ?? "",
                'uploader' => $uploader,
                'project_id' => $projectId,
                'status' => $status,
                'uid' => uniqid($randomString . $processId),
                'created_at' => now(),
                'author_id' => $author_id ? $author_id  : null,
                'md5' => $md5,
                'code' => $video['code'] ?? "",
                'source' => $source,
                'others' => $others,
                'size' => $size ?? 0,
                'resolution' => $resolution?? 0,
                'resolution_tag' => $resolution_tag ?? 0,
                'server_id' => $server_id,
                'cover_photo' => $video['image'] ?? "",
                'cover_vertical' => $video['image_vertical'] ?? "",
                'remark' => $video['remark'] ?? ""
            ]);
            $id = $videoInsert->id;
            if($status == 5){
                $videoChoose = VideoChoose::create([
                    'project_id' => $projects->id,
                    'created_by' => $uploader,
                    'created_at' => now(),
                    'video_id' => $id,
                    'status' => 1
                ]);
                
                $temp = new \stdClass();
                $temp->theme = $themeId;
                $temp->rule = $ruleId;
                $videoChoose = VideoChoose::cutStatus($temp, $videoChoose->id);
            }
            if($subtitleContents){
                $directory = 'subtitle/'. $id;
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                
                Storage::disk('public')->put($directory . '/subtitle.srt', $subtitleContents);
                $videoInsert->subtitle = '/storage/' . $directory . '/subtitle.srt';
                $videoInsert->save();
            }
            $videoArray = [];
            $videoArray = ['types' => $types];
            $videoInsert->types()->sync($types);
            if(count($tags) > 0){
                $videoArray = ['tags' => $tags];
                $videoInsert->tags()->sync($tags);
            }
            Video::processSaveLog($videoArray, $videoInsert, 1);
            return $videoInsert;
        }elseif($update){
            $id = $videoDB->id;
            $original = Video::getManyRelationModel($videoDB);
            $data = [
                'title' => $video['title'] ?? "",
                'description' => $video['description'] ?? "",
                'author_id' => $author_id ? $author_id  : null,
                'source' => $source,
                'others' => $others,
            ];
            $videoDB->update($data);
            $videoDB->types()->sync($types);
            $videoDB->tags()->sync($tags);
            $data['tags'] = $tags;
            $data['types'] = $types;
            Video::processSaveLog($data, $videoDB, 2, $original);
            return $videoDB;
        }
    }

    public static function assign($total, $user_id){
        if(Auth::user()->isReviewer()){
            $videos = Video::join('video_types', 'videos.id', '=', 'video_types.video_id')
                ->join('types', 'video_types.type_id', '=', 'types.id')
                ->orderBy('types.assigned_order','desc')
                ->orderBy('videos.created_at','desc')
                ->where('videos.status', 1)
                ->whereNull('videos.assigned_to')
                ->select('videos.*')
                ->limit($total)->get();
            $videoCount = $videos->count();
            $now = now();
            foreach($videos as $video){
                $video->update([
                    'assigned_to' => $user_id,
                    'assigned_at' => $now
                ]);
            }
            $videoExtraCount = 0;
            if($videoCount < $total){
                $left = $total - $videoCount;
                $videosExtras = Video::orderBy('videos.created_at','desc')->where('status', 1)->whereNull('videos.assigned_to')
                    ->limit($left)->get();
                $videoExtraCount = $videosExtras->count();
                foreach($videosExtras as $videosExtra){
                    $videosExtra->update([
                        'assigned_to' => $user_id,
                        'assigned_at' => $now
                    ]);
                }
            }
            $totalVideo = $videoCount + $videoExtraCount;
        }else{
            $types = Config::getCachedConfig('cover_assigned_type');
            $project_id = Config::getCachedConfig('cover_assigned_project_id_first');
            if($types){
                $types = explode(",",$types);
            }else{
                $types = [];
            }
           $query = Video::join('video_types', 'videos.id', '=', 'video_types.video_id')
                ->join('types', 'video_types.type_id', '=', 'types.id')
                ->whereIn('types.id', $types)
                ->where('videos.status', 3)
                ->where('videos.cover_status', '!=', 2)
                ->whereNull('videos.cover_assigned_to')
                ->select('videos.*');
            if ($project_id) {
                $query->orderByRaw('FIELD(videos.project_id, ?) DESC', [$project_id]);
            }
            $videos = $query
                ->orderBy('videos.created_at', 'desc')
                ->limit($total)
                ->get();
            $videoCount = $videos->count();
            $now = now();
            foreach($videos as $video){
                $video->update([
                    'cover_assigned_to' => $user_id,
                    'cover_assigned_at' => $now
                ]);
            }
            $totalVideo = $videoCount;
        }
        
        return $totalVideo;
    }

    public static function moveVideo($video_path, $cover_path, $server_id, $video_id = 0)
    {
        if($cover_path){
            $temp = array('video' => $video_path, 'image' => $cover_path);
        }else{
            $temp = array('video' => $video_path);
        }
        $server = Server::findOrFail($server_id);
        $response = Helper::sendResourceRequest(
            $server->domain . "/api/backup/file",
            json_encode($temp),
            array('Content-Type: application/json'),
            'Move Direct Cut Video '.$video_id
        );
        $response = json_decode($response);
        return $response;
    }

    public static function baseCheckLanDomain($play_domain,$path,$cover_photo='',$cover_vertical='')
    {
        $header = self::checkFileHeader($play_domain . "/public" . $path);
        if($header == 999999){
            throw new \Exception('网络波动，无法检查视频资源');
        }else if(!$header){
            throw new \Exception('视频资源损坏');
        }
      
        if($cover_photo){
            $header2 = self::checkFileHeader($play_domain . "/public" . $cover_photo);
            if($header2 == 999999){
                throw new \Exception('网络波动，无法检查封面图资源');
            }else if(!$header2){
                throw new \Exception('封面图资源损坏');
            }
            if($header!=$header2){
                throw new \Exception('封面图服务器与视频服务器地址不一致.请联系运维人员确认资源地址.');
            }
        }

        if($cover_vertical){
            $header3 = self::checkFileHeader($play_domain . "/public" . $cover_vertical);
            if($header3 == 999999){
                throw new \Exception('网络波动，无法检查竖图资源');
            }else if(!$header3){
                throw new \Exception('竖图资源损坏');
            }
            if($header!=$header3){
                throw new \Exception('竖图服务器与视频服务器地址不一致.请联系运维人员确认资源地址.');
            }
        }

        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $header, $matches);
        $lan_ip = $matches[1] ?? $header;
        $server = Server::where('lan_domain','like', '%'.$lan_ip . '%')->first();
        if(!$server){
            throw new \Exception('资源损坏。');
        }
        return $server->id;
    }

    public static function checkLanDomain($video){
        $server_id = self::baseCheckLanDomain($video->servers->play_domain,$video->path,$video->cover_photo,$video->cover_vertical);
        $video->server_id = $server_id;
        $video->save();
        return $server_id;
    }

    public static function checkFileHeader($link){
        $ch = curl_init();

        // Set the URL
        curl_setopt($ch, CURLOPT_URL, $link);


        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); 

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        FacadesLog::channel('send_api')->info('Curl Check--'.$link . "--" .$httpCode. "--" . $response);
        if($httpCode === 0){
            return 999999;
        }else if($httpCode === 502){
            return 999999;
        }else if($httpCode != 200){
            return '';
        }

        $headers_array = explode("\r\n", $headers);
        $x_upstream_header = null;

        foreach ($headers_array as $header) {
            if (stripos($header, 'X-Upstream:') === 0) {
                $x_upstream_header_string = trim(substr($header, strlen('X-Upstream:')));
                break;
            }
        }

        $x_upstream_header_array = explode(",",$x_upstream_header_string);
        $x_upstream_header = trim(end($x_upstream_header_array));

        FacadesLog::channel('send_api')->info('Curl Check--'.$link . "--" .$httpCode. "--" . $x_upstream_header_string . "--" . $x_upstream_header);

        return $x_upstream_header;
    }
}
