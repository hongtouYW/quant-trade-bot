<?php

namespace App\Models;

use App\Http\Helper;
use App\Models\Log as ModelsLog;
use App\Trait\LogWithManyToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Telegram\Bot\Laravel\Facades\Telegram;

class VideoChoose extends Model
{
    use LogWithManyToMany;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'created_by',
        'created_at',
        'video_id',
        'status',
        'server'
    ];

    public const STATUS = [
        '1' => '已预选',
        '2' => '切片中',
        '3' => '切片失败',
        '4' => '切片成功/同步失败',
        '5' => '同步中',
        '6' => '重新审核/视频不通过',
        '7' => '发送成功',
        '8' => '同步成功/发送失败',
        '9' => '生成字幕中',
        '10' => '生成字幕失败',
        '11' => '重试生成字幕',
        '12' => '发送字幕失败',
        '13' => '准备字幕失败'
    ];

    public const BELONGTOMANY = [
        'types'
    ];

    public const CUT_BTN = 'cut-status-model-btn';
    public const TITLE = '视频预选区';
    public const CRUD_ROUTE_PART = 'videoChoose';

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function cutAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function syncAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function callbackAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    protected function aiAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
        );
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

   public function types()
    {
        return $this->belongsToMany(
            ProjectTypes::class,
            'video_choose_project_types',
            'video_chooses_id',  
            'project_types_id'  
        );
    }

    public function projectRule()
    {
        return $this->belongsTo(ProjectRules::class, 'project_rule_id');
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
            $query->whereNotIn('video_chooses.status', [1]);
        }else{
            $query->where('video_chooses.status', 1);
        }
        if ($request->status !== null) {
            if(Auth::user()->checkUserRole([1])){
                $query->where(function ($q) use ($request) {
                    $q->where('video_chooses.status', $request->status);
                });
            }else{
                if($request->status == 2){
                    $query->where(function ($q) use ($request) {
                        $q->whereIn('video_chooses.status', [2,9,11]);
                    });
                }else if($request->status == 3){
                    $query->where(function ($q) use ($request) {
                        $q->whereIn('video_chooses.status', [3,10,12,13]);
                    });
                }else{
                    $query->where(function ($q) use ($request) {
                        $q->where('video_chooses.status', $request->status);
                    });
                }
            }
        }

        if ($request->created_by !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->created_by);
            });
        }

        if ($request->project_id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('project_id', $request->project_id);
            });
        }

        if ($request->video_id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('video_id', $request->video_id);
            });
        }

        if ($request->tag !== null) {
            $query->whereHas('video.tags', function ($q) use ($request) {
                $q->where("tags.id", $request->tag);
            });
        }

        if ($request->type !== null) {
            $query->whereHas('video.types', function ($q) use ($request) {
                $q->where("types.id", $request->type);
            });
        }

        if ($request->source !== null) {
            $query->whereHas('video', function ($q) use ($request) {
                $q->where('source', 'like', '%'. $request->source . '%');
            });
        }

        if ($request->id !== null) {
            $query->where(function ($q) use ($request) {
                $q->where('video_chooses.id', $request->id);
            });
        }

        if ($request->cut_at_from !== null || $request->cut_at_to !== null) {
            if($request->cut_at_from !== null && $request->cut_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('cut_at', '>=' ,$request->cut_at_from. " 00:00:00")
                    ->where('cut_at', '<' ,$request->cut_at_to . " 23:59:59");
                });
            }else if($request->cut_at_from !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('cut_at', '>=' ,$request->cut_at_from. " 00:00:00");
                });
            }else if($request->cut_at_to !== null){
                $query->where(function ($q) use ($request) {
                    $q->where('cut_at', '<=' ,$request->cut_at_to. " 23:59:59");
                });
            }
           
        }

        if ($request->project_types !== null) {
            if(!Auth::user()->isSuperAdmin()){
                $query->whereHas('types', function ($q) use ($request) {
                    $q->where("project_types.id", $request->project_types);
                });
            }else{
                $project_types_filter = ProjectTypes::where('name','like', '%'. $request->project_types . '%')->first();
                if($project_types_filter){
                    if($project_types_filter->id){
                        $query->whereHas('types', function ($q) use ($project_types_filter) {
                            $q->where("project_types.id", $project_types_filter->id);
                        });
                    }
                }
            }
           
        }

        if($request->history){
            $query->orderBy('video_chooses.cut_at', 'desc');
        }else{
            $query->orderBy('video_chooses.created_at', 'desc');
        }

        return $query;
    }

    public static function getTableRowAction($row, $crudRoutePart, $isButton = 1, $history = 0)
    {
        $html = "";
        $user = Auth::user();
        $user->loadMissing('projects.servers');
        $project = $user->projects->first();
        $userProjectIds = $user->projects->pluck('id')->toArray();
        $cutBtnClass = 'no-server';
        if($user->checkUserRole([1, 2])){
            $cutBtnClass = VideoChoose::CUT_BTN;
        }else{
            if($project){
                $server = $project->servers->first();
                if($server->ip ?? ''){
                    $cutBtnClass = VideoChoose::CUT_BTN;
                }
            }
        }

        if ($row->status == 1) {
            $html .= view('widget.changeStatusButtons', [
                'confirmWord' => "确定不预选吗？",
                'crudRoutePart' => $crudRoutePart . '.changeStatus',
                'id' => $row->id,
                'title' => '不预选',
                'class' => 'btn-warning bottom-margin-10',
                'value' => 0,
                'isButton' => $isButton,
                'modalBtnClass' => '',
                'extra' => 0,
            ]);
            if($user->checkUserRole([1,2,6])){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定切片吗？",
                    'crudRoutePart' => $crudRoutePart . '.cutStatus',
                    'id' => $row->id,
                    'title' => '切片',
                    'class' => 'btn-info bottom-margin-10',
                    'value' => 2,
                    'extra' => $row->project_id,
                    'isButton' => $isButton,
                    'modalBtnClass' => $cutBtnClass,
                ]);
            }
        }

        $showRetryButton = true;
        if($userProjectIds){
            if (in_array(Project::SHORTSTORY, $userProjectIds)){
                 $showRetryButton = false;
            }
        }
        if($showRetryButton){
            if($user->checkUserRole([1,2,6])){
                if (($row->status == 3 || $row->status == 13)
                    || $user->checkUserRole([1])) {
                    $recutTimes = Config::getCachedConfig('recut_times') ?? 1;
                    if($row->recut_time < $recutTimes){
                        if($row->projectRule){
                            $html .= view('widget.changeStatusButtons', [
                                'confirmWord' => "确定重新切片吗？",
                                'crudRoutePart' => $crudRoutePart . '.changeStatus',
                                'id' => $row->id,
                                'title' => '重新切片',
                                'class' => 'btn-info bottom-margin-10',
                                'value' => 2,
                                'isButton' => $isButton,
                                'modalBtnClass' => '',
                                'extra' => $row->project_id,
                            ]);
                        }else{
                            $html .= view('widget.changeStatusButtons', [
                                'confirmWord' => "确定重新切片吗？",
                                'crudRoutePart' => $crudRoutePart . '.cutStatus',
                                'id' => $row->id,
                                'title' => '重新切片',
                                'class' => 'btn-info bottom-margin-10',
                                'value' => 2,
                                'isButton' => $isButton,
                                'modalBtnClass' => $cutBtnClass,
                                'extra' => $row->project_id,
                            ]);
                        }
                    }
                }
                if($row->status == 4){
                    $html .= view('widget.changeStatusButtons', [
                        'confirmWord' => "确定重新同步吗？",
                        'crudRoutePart' => $crudRoutePart . '.changeStatus',
                        'id' => $row->id,
                        'title' => '重新同步',
                        'class' => 'btn-info bottom-margin-10',
                        'value' => 2,
                        'isButton' => $isButton,
                        'modalBtnClass' => '',
                        'extra' => 0,
                    ]);
                }else if($row->status == 8 || $row->status == 7){
                    $html .= view('widget.changeStatusButtons', [
                        'confirmWord' => "确定重新发送吗？",
                        'crudRoutePart' => $crudRoutePart . '.changeStatus',
                        'id' => $row->id,
                        'title' => '重新发送',
                        'class' => 'btn-info bottom-margin-10',
                        'value' => 7,
                        'isButton' => $isButton,
                        'modalBtnClass' => '',
                        'extra' => 0,
                    ]);
                }
            }

            if($user->checkUserRole([1,2]) && $row->status != 1 && $row->video?->status != 4){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定重新预选吗？",
                    'crudRoutePart' => $crudRoutePart . '.changeStatus',
                    'id' => $row->id,
                    'title' => '重新预选',
                    'class' => 'btn-warning bottom-margin-10',
                    'value' => -1,
                    'isButton' => $isButton,
                    'modalBtnClass' => '',
                    'extra' => 0,
                ]);
            }elseif($user->checkUserRole([6]) && $row->status == 7){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定重新预选吗？",
                    'crudRoutePart' => $crudRoutePart . '.changeStatus',
                    'id' => $row->id,
                    'title' => '重新预选',
                    'class' => 'btn-warning bottom-margin-10',
                    'value' => -1,
                    'isButton' => $isButton,
                    'modalBtnClass' => '',
                    'extra' => 0,
                ]);
            }

            if($user->checkUserRole([1,2,6]) && $row->status == 12){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定重新发送字幕吗？",
                    'crudRoutePart' => $crudRoutePart . '.changeStatus',
                    'id' => $row->id,
                    'title' => '重新发送字幕',
                    'class' => 'btn-info bottom-margin-10',
                    'value' => 9,
                    'isButton' => $isButton,
                    'modalBtnClass' => '',
                    'extra' => 0,
                ]);
            }

            if($user->checkUserRole([1,2,6]) && $row->status == 10){
                $html .= view('widget.changeStatusButtons', [
                    'confirmWord' => "确定重新生成字幕吗？",
                    'crudRoutePart' => $crudRoutePart . '.changeStatus',
                    'id' => $row->id,
                    'title' => '重新生成字幕',
                    'class' => 'btn-info bottom-margin-10',
                    'value' => 10,
                    'isButton' => $isButton,
                    'modalBtnClass' => '',
                    'extra' => 0,
                ]);
            }

            if($user->checkUserRole([1]) && $history == 1){
                if($row->video?->status != 4){
                    $confirmWord = "确定下架吗？";
                    $project_name = implode(",", $row->video->activeProjects->pluck('name')->toArray());
                    if ($project_name) {
                        $confirmWord = '视频已被 '.$project_name .' 切片/同步。是否继续下架?';
                    }
                    $html .= view('widget.changeStatusButtons', [
                        'confirmWord' =>  $confirmWord,
                        'crudRoutePart' => $crudRoutePart . '.changeStatusModal',
                        'id' => $row->id,
                        'title' => '下架',
                        'class' => 'reset-checking-btn bottom-margin-10',
                        'value' => 4,
                        'isButton' => $isButton,
                        'modalBtnClass' => Video::REREVIEW_BTN,
                        'extra' => 0,
                    ]);
                }
            }
        }

        if($row->status == 7 || $row->status == 8){
            if ($isButton) {
                $html .= "<a href='".route('videoChoose.callback',['id'=>$row->id])."' class='btn btn-sm btn-secondary waves-effect waves-light bottom-margin-10'>回调json</a>";
            } else {
                $html .= "<a href='".route('videoChoose.callback',['id'=>$row->id])."'>回调json</a>";
            }
        }

        if($user->checkUserRole([1])){
            if ($html != "" && $isButton) {
                $html .= '<hr>';
            }
            $html .= view('widget.viewActionButtons', [
                'crudRoutePart' => self::CRUD_ROUTE_PART,
                'id' => $row->id,
                'isButton' => $isButton
            ]);
        }

        return $html;
    }

    public static function chageStatus($videoChoose, $status, $check = 1){
        $redis = Redis::connection('default');
        $result = $redis->select(3);
        if(!$result){
            throw new \Exception('Redis DB 错误');
        }
        $videoChooseId = $videoChoose->id;
        if(!Auth::user()?->checkUserRole([1])){
            if($check){
                if($status == 2){
                    if($videoChoose->status == 10){
                        $status = 10;
                    }elseif($videoChoose->status == 12){
                        $status = 9;
                    }
                }
                $userId = Auth::user()?->id;
                $key = "lock:user:$userId:videoChooseId:$videoChooseId";
                if ($redis->exists($key)) {
                    throw new \Exception('视频已更换状态,请24小时候再尝试更换状态');
                }
            }
            ModelsLog::create([
                'type' => 2,
                'user' => Auth::user()?->id ?? 0,
                'data' => json_encode(['changeStatus' => $status, 'videoChooseId'=> $videoChooseId]),
                'model' => 'VideoChoose',
                'target_id' => $videoChooseId,
                'ip' => Helper::getIp()
            ]);
        }

        switch ($status) {
            case 0:
                $videoChoose->delete();
                break;
            case -1:
                if($videoChoose->status == 6){
                    throw new \Exception('视频已下架无法重新预选');
                }
                $videoChoose->delete();
                $videoChoose = VideoChoose::create([
                    'project_id' => $videoChoose->project_id,
                    'created_by' => $videoChoose->created_by,
                    'created_at' => now(),
                    'video_id' => $videoChoose->video_id,
                    'status' => 1
                ]);
                VideoChoose::processSaveLog([], $videoChoose, 1, []);
                break;
            case 2:
                $recutTimes = Config::getCachedConfig('recut_times') ?? 1;
                if($videoChoose->projectRule && $videoChoose->recut_time < $recutTimes){
                    $temp = new \stdClass();
                    $temp->theme = $videoChoose->types?->pluck('id')->toArray();
                    $temp->rule = $videoChoose->project_rule_id;
                    $videoChoose = VideoChoose::cutStatus($temp, $videoChoose->id, 1);
                }
                break;
            case 5:
                $videoChoose = VideoChoose::syncVideoToCustomer($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                break;
            case 7:
                $videoChoose = VideoChoose::sendVideoCallbackToCustomer($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                break;
            case 9:
                $videoChoose = Helper::resendSubtitle($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                break;
            case 10:
                $videoChoose = Helper::retrySubtitle($videoChoose);
                if($videoChoose){
                     $videoChoose->save();
                    VideoChoose::processSaveLog([], $videoChoose, 2, []);
                }
        }
        if(!Auth::user()?->checkUserRole([1])){
            if($check){
                $redis->setex($key, 86400, true);
            }
        }
        return $videoChoose;
    }

    public static function cutStatus($request, $id, $api = 0, $check = 1){
        try {
            if(!$request->rule){
                throw new \Exception('找不到规则');
            }
            $videoChoose = VideoChoose::findOrFail($id);
            if($check){
                if($videoChoose->status == 2){
                    throw new \Exception('已切片');
                }
            }
            $recutTimes = Config::getCachedConfig('recut_times') ?? 1;
            if(!$api){
                if($videoChoose->recut_time >= $recutTimes){
                    throw new \Exception('资源损坏');
                }
            }
            $types = [];
            foreach (($request->theme ?? []) as $typeId) {
                $types[$typeId] = [
                    'video_id'   => $videoChoose->video_id,
                    'video_chooses_id' => $videoChoose->id,
                ];
            }
            $videoChoose->types()->sync($types);
            $videoChoose->project_rule_id = $request->rule;
            $video = $videoChoose->video;
            if($check){
                $videoServerId = Video::checkLanDomain($video);
            }else{
                try{
                    $videoServerId = Video::checkLanDomain($video);
                }catch (\Exception $e) {
                    $videoChoose->status = 3;
                    $videoChoose->cut_callback_msg = $e->getMessage();
                    $videoChoose->save();
                    VideoChoose::processSaveLog([], $videoChoose, 2, []);
                    return '';
                }
            }
            
            $videoServer = Server::findOrFail($videoServerId);
            $rule = ProjectRules::findOrFail($request->rule);
            $headVideo = $rule->head_video ? asset($rule->head_video): "";
            $adImage = $rule->ad_image ? asset($rule->ad_image): "";
            $logoImage = $rule->logo_image ? asset($rule->logo_image): "";
            $subtitleLink = $video->subtitle ? asset($video->subtitle): "";
            $domain = $videoServer->domain;
            $project = Project::findOrFail($videoChoose->project_id);
            $project_server = $project->servers->first();
            if(!$project_server){
                $videoChoose->status = 4;
                $videoChoose->sync_callback_msg = 'No Project Server Set';
                self::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
                throw new \Exception($videoChoose->sync_callback_msg);
            }
            $data = [
                'identifier' => $video->uid . "__" . $videoChoose->id,
                'video' => $domain . '/download' . $video->path,
                'cover' => $video->cover_photo ? $domain . '/download' . $video->cover_photo : '',
                'coverVer' => $video->cover_vertical ? $domain . '/download' . $video->cover_vertical : '',
                'projectId' => $videoChoose->project_id,
                'rule' => [
                    'resolutionOption' => (int)$rule->resolution_option,
                    'webp' => [
                        'enable' => boolval($rule->webp_enable),
                        'start' => (int)$rule->webp_start,
                        'interval' => (int)$rule->webp_interval,
                        'count' => (int)$rule->webp_count,
                        'length' => (int)$rule->webp_length,
                    ],
                    'skipVideo'=> [
                        'enable' => boolval($rule->skip_enable),
                        'head' => (int)$rule->skip_head,
                        'back' => (int)$rule->skip_back,
                    ],
                    'headVideo' => [
                        'enable' => boolval($rule->head_enable),
                        'video' => $headVideo,
                    ],
                    'ad' => [
                        'enable' => boolval($rule->ad_enable),
                        'image' => $adImage,
                        'start' => (int)$rule->ad_start,
                    ],
                    'logo' => [
                        'enable' => boolval($rule->logo_enable),
                        'image' => $logoImage,
                        'position' => (int)$rule->logo_position,
                        'padding' => (int)$rule->logo_padding,
                        'scale' => (int)$rule->logo_scale,
                    ],
                    'font' => [
                        'enable' => boolval($rule->font_enable),
                        'text' => $rule->font_text,
                        'color' => $rule->font_color,
                        'size' => (int)$rule->font_size,
                        'position' => (int)$rule->font_position,
                        'interval' => (int)$rule->font_interval,
                        'scroll' => (int)$rule->font_scroll,
                        'border' => (int)$rule->font_border,
                        'shadow' => boolval($rule->font_shadow),
                        'space' => (int)$rule->font_space,
                    ],
                    'preview' => [
                        'enable' => boolval($rule->preview_enable),
                        'interval' => (int)$rule->preview_interval,
                        'skip' => (int)$rule->preview_skip,
                    ],
                    'm3u8' => [
                        'enable' => boolval($rule->m3u8_enable),
                        'encrypt' => boolval($rule->m3u8_encrypt),
                        'interval' => (int)$rule->m3u8_interval,
                        'bitrate' => (int)$rule->m3u8_bitrate,
                        'fps' => (int)$rule->m3u8_fps,
                    ],
                    'subtitle' => [
                        'enable' => boolval(($video->subtitle ? 1 : 0)),
                        'file' => $subtitleLink,
                    ],
                    'aiSubtitle' => [
                        'enable' => boolval($rule->generate_subtitle),
                        'language' => $rule->subtitle_languages ? json_decode($rule->subtitle_languages) : null,
                    ],
                ],
                'receiver' => [
                    'username' => $project_server->username,
                    'host' => $project_server->ip,
                    'port' => (int)$project_server->port,
                    'identifier' => $videoChoose->video?->uid . "__" . $videoChoose->id,
                    'path' => $project_server->absolute_path,
                ]
            ];
            $json = json_encode($data);
            $project = $videoChoose->project;
            $redis = Redis::connection('default');
            $redis_db = $project->redis_db;
            if($video->resolution_tag == 1){
                if($project->enable_4k){
                    $redis_db = Config::getCachedConfig('redis_db_4k') ?? 6;
                }
            }
            $result = $redis->select($redis_db);
            if(!$result){
                throw new \Exception('Redis DB 错误');
            }
            $result = $redis->set('projects', $project->name);
            if(!$result){
                throw new \Exception('Redis 无法储存');
            }
            
            if($project->daily_cut < $project->daily_cut_quota){
                $result = $redis->lpush('downloader2', $json);
            }else{
                $result = $redis->rpush('downloader2', $json);
            }
            $videoChoose->cut_callback_msg = "";
            $videoChoose->cut_at = now();
            if ($result !== false) {
                if(!$api){
                    if($videoChoose->status == 3){ 
                        $videoChoose->recut_time = $videoChoose->recut_time + 1;
                    }
                }
                $project->daily_cut = $project->daily_cut + 1;
                $project->save();
                $videoChoose->status = 2;
                $videoChoose->project_rules = $json;
                $videoChoose->project_rule_id = $rule->id;
                Log::channel('send_api')->info('Video Cut Redis--'.$redis_db.'--'.$json);
            }else{
                $videoChoose->status = 3;
                $videoChoose->cut_callback_msg = "切片Redis错误";
                self::sendErrorMessageToTelegram($videoChoose->cut_callback_msg, $videoChoose->id,$videoChoose->project, '切片错误', $videoChoose->video_id);
                Log::channel('api_error')->info('Video Cut Redis--'.$redis_db.'--'.$json);
            }
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            return $videoChoose;
        } catch (\Exception $e) {
            $videoChoose->status = 3;
            $videoChoose->cut_callback_msg = $e->getMessage();
            if($e->getMessage() == '封面图服务器与视频服务器地址不一致.请联系运维人员确认资源地址.'
            || $e->getMessage() == '竖图服务器与视频服务器地址不一致.请联系运维人员确认资源地址.'
            || $e->getMessage() == '视频资源损坏'
            || $e->getMessage() == '封面图资源损坏'
            || $e->getMessage() == '竖图资源损坏'
            || $e->getMessage() == '资源损坏。'){
                $errorTypeCut = '切片检查错误';
            }else{
                $errorTypeCut = '切片错误';
            }
            self::sendErrorMessageToTelegram($videoChoose->cut_callback_msg, $videoChoose->id,$videoChoose->project, $errorTypeCut, $videoChoose->video_id);
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            throw new Exception($e->getMessage());
        }
    }

    public static function getCallbackMessage($videoChoose){
        $video = $videoChoose->video;
        if($videoChoose->cut_callback_success_msg){
            $cut_callback_msg = explode("--", $videoChoose->cut_callback_success_msg);
        }else{
            $cut_callback_msg = explode("--", $videoChoose->cut_callback_msg);
        }
        $cut_callback_msg = $cut_callback_msg[0];
        $data = [
            'uid' => $video->uid,
            'video_id' => $video->id,
            'title' => $video->title,
            'video' => json_decode($cut_callback_msg),
            'description' => $video->description,
            'code' => $video->code,
            'author' => $video->author ? $video->author->name : "",
            'type' => $video->types->pluck('name')->toArray(),
            'tag' => $video->tags->pluck('name')->toArray(),
            'theme' => $videoChoose->types->pluck('name')->toArray(),
            'remark' => $video->remark ?? ""
        ];
        $project = Project::findOrFail($videoChoose->project_id);
        $project_server = $project->servers->first();
        $base_path = $project_server->path . "/" .  $video->uid . "__" . $videoChoose->id  . "/";
        if($videoChoose->subtitle_callback_msg){
            $subtitle = (json_decode($videoChoose->subtitle_callback_msg,true) ?? []);
            foreach($subtitle as $language => $files){
                foreach($files as $key => $file){
                    $path = $base_path . "subtitles/" . $file;
                    $filename = basename($path);
                    $subtitle_key = '';
                    switch ($filename) {
                        case 'seo.txt':
                            $subtitle_key = 'seo';
                            break;
                        case 'summary.txt':
                            $subtitle_key = 'summary';
                            break;
                        case 'keyword.txt':
                            $subtitle_key = 'keyword';
                            break;
                        case 'subtitles.srt':
                            $subtitle_key = 'subtitle_srt';
                            break;
                        case 'subtitles.vtt':
                            $subtitle_key = 'subtitle_vtt';
                            break;
                    }
                    $data['subtitle'][$language][$subtitle_key] =  $base_path . "subtitles/" . $file;
                }
            }
        }
        return $data;
    }

    public static function printJsonTree($data) {
        $string = "";
        $string .= '<ul>';
        foreach ($data as $key => $value) {
            $string .= '<li><span class="json-key">' . $key . '</span>: ';
            if (is_object($value)) {
                $value = (array) $value;
            }
            if (is_array($value)) {
                $string .= self::printJsonTree($value);
            } else {
                $string .= '<span class="json-value">' . $value . '</span>';
            }
            $string .= '</li>';
        }
        $string .= '</ul>';
        return $string;
    }

    public static function sendVideoCallbackToCustomer($videoChoose){
        $rule = ProjectRules::findorFail($videoChoose->project_rule_id);
        if(!$rule){
            $videoChoose->status = 8;
            $videoChoose->send_callback_msg = 'Rules Callback Url Not Found';
            self::sendErrorMessageToTelegram($videoChoose->send_callback_msg, $videoChoose->id,$videoChoose->project, '发送错误', $videoChoose->video_id);
            return $videoChoose;
        }   
        $data = self::getCallbackMessage($videoChoose);
        list($response, $ip) = Helper::sendResourceIpRequest(
            $rule->callback_url,
            json_encode($data),
            array('Content-Type: application/json'),
            'Send Video Info To Customer Api'
        );
        $response = json_decode($response);
        $status = 8;
        $send_callback_msg = '';
        if (($response->code ?? 0) == 200) {
            $status = 7;
            $send_callback_msg = 'OK';
        }else{
            if($response){
                if($response->msg ?? ''){
                    if(gettype($response->msg) == 'string'){
                        $send_callback_msg = $response->msg;
                    }else{
                        $send_callback_msg = json_encode($response->msg);
                    }   
                }elseif($response->error ?? ''){
                    if(gettype($response->error) == 'string'){
                        $send_callback_msg = $response->error;
                    }else{
                        $send_callback_msg = json_encode($$response->error);
                    }
                }else{
                    if(gettype($response) == 'string'){
                        $send_callback_msg = $response;
                    }else{
                        $send_callback_msg = json_encode($response);
                    }
                }
                self::sendErrorMessageToTelegram($send_callback_msg, $videoChoose->id,$videoChoose->project, '发送错误', $videoChoose->video_id);
            }else{
                $send_callback_msg = 'Failed Without Response';
            }
        }
        $videoChoose->send_callback_ip = $ip;
        $videoChoose->send_callback_msg = $send_callback_msg;
        $videoChoose->status = $status;
        $videoChoose->callback_at = now();
    
        return $videoChoose;
    }

    public static function syncVideoToCustomer($videoChoose){
        $project = Project::findOrFail($videoChoose->project_id);
        $project_server = $project->servers->first();
        if(!$project_server){
            $videoChoose->status = 4;
            $videoChoose->sync_callback_msg = 'No Project Server Set';
            self::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
            return $videoChoose;
        }
        $data = [
            'username' => $project_server->username,
            'host' => $project_server->ip,
            'port' => (int)$project_server->port,
            'identifier' => $videoChoose->video?->uid . "__" . $videoChoose->id,
            'path' => $project_server->absolute_path,
        ];
        $status = 4;
        $sync_callback_msg = '同步Redis错误';
        if($videoChoose->server){
            $cutServer = CutServer::where('ip',$videoChoose->server)->first();
            if($cutServer->ip ?? ''){
                config(['database.redis.cut' => [  
                    'host' => $cutServer->ip,
                    'password' => $cutServer->redis_password,
                    'port' => $cutServer->redis_port,
                    'database' => $cutServer->redis_db,
                    'username' => ''
                ]]);
        
                $redis = Redis::connection('cut');
                $json = json_encode($data);
                $result = $redis->rpush('sender', $json);
                
                if ($result !== false) {
                    $status = 5;
                    $sync_callback_msg = '';
                }
            }else{
                $json = '返回的切片资源服务器与设置的切片资源服务器不匹配';
                $sync_callback_msg = "返回的切片资源服务器与设置的切片资源服务器不匹配";
                $status = 4;
            }
        }
        if($sync_callback_msg == '同步Redis错误'){
            Log::channel('api_error')->info('Video Sync Redis--'.$json . '--' . $cutServer->ip);
        }else{
            Log::channel('send_api')->info('Video Sync Redis--'.$json . '--' . $cutServer->ip);
        }
        if($status == 4){
            self::sendErrorMessageToTelegram($sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
        }
        $videoChoose->sync_at = now();
        $videoChoose->status = $status;
        $videoChoose->sync_callback_msg = $sync_callback_msg;
        return $videoChoose;
    }

    public static function sendErrorMessageToTelegram($msg, $id, $project, $errorType, $videoId){
        if($errorType == '切片错误'){
            if (strpos($msg, 'send error:') !== false) {
                $errorType = '字幕服务错误';
            }
        }

        $chatId = Config::getCachedConfig('telegram_bot_chat_id') ;
        $text = $project->name . " (" . $project->id . ")\nId:" . $id . "\nVideo Id:" . $videoId . "\n\n" . $errorType . "\n" . $msg;
                
        $maxLength = 4090;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }
        
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' =>  $text,
        ]);
    }
}
