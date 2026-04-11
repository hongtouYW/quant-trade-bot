<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Author;
use App\Models\Config;
use App\Models\Ftp;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\ProjectTypes;
use App\Models\Server;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * 上传手只能看到，编辑及删除自己的视频
 */
class VideoController extends Controller
{
    public function __construct()
    {
        $this->init(Video::class);
        parent::__construct();
    }

    public function successedIndex(Request $request)
    {
        $request->merge(['success' => 1]);
        return $this->index($request);
    }

    public function reReviewIndex(Request $request)
    {
        $request->merge(['reReview' => 1]);
        return $this->index($request);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Video::with([
                    'tags', 
                    'types',
                    'servers',
                    'firstApprovedByUser',
                    'chooseProject',
                    'uploaderUser',
                    'projects',
                    'assignedCoverUser',
                    'uploaderUser',
                    'reReviewUser',
                    'firstApprovedByUser',
                    'assignedUser'
                ])
                ->search($request)->select(sprintf('%s.*', (new Video())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                return Video::getTableRowAction($row, $this->crudRoutePart);
            });

            $table->editColumn('details', function ($row) {
                $tag_labels = [];
                foreach ($row->tags as $tag) {
                    $tag_labels[] = sprintf('<span class="label filter-click" key="tag" filter="'. $tag->id .'">%s</span>', strip_tags($tag->name));
                }
                $type_labels = [];
                foreach ($row->types as $type) {
                    $type_labels[] = '<span class="label2 filter-click" key="type" filter="'. $type->id .'">' . strip_tags($type->name) . '</span>';
                }
                if ($row->path) {
                    $path = $row->servers->play_domain . '/public' . $row->path;
                } else {
                    $path = "";
                }
                $author = "";
                if ($row->author) {
                    $author = $row->author->name;
                }

                $project_type_labels = [];
                $projectTypes = $row->projectTypes()->where('is_show', 1)->whereNotNull('show_name')->get();
                foreach ($projectTypes as $type) {
                    $project_type_labels[] = '<span class="label3">' . strip_tags($type->show_name) . '</span>';
                }
                return  '<b>uid : </b>' . strip_tags($row->uid) . '<br>' .
                    '<b>标题 : </b><a target="_blank" href="' . $path . '">' . strip_tags($row->title) . '<br></a>' .
                    '<b>作者 : </b>' . strip_tags($author) . '<br>' .
                    '<b>分辨率 : </b>' . strip_tags($row->resolution) . '<br>' .
                    '<b>大小 : </b>' . strip_tags($row->size) . '<br>' .
                    '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
                    '<b>分类 : </b>' . implode(' ', $type_labels) . '<br>' .
                    '<b>片商 : </b>' . strip_tags($row->source) . '<br>' .
                    '<b>资源来源 : </b>' . strip_tags($row->website) . '<br>' .
                    '<b>主题 : </b>' . implode(' ', $project_type_labels) . '<br>' .
                    '<b>字幕 : </b>' . ($row->subtitle ? "有" : "没有"). '<br>' . 
                    '<b>Remark : </b>' . ($row->remark ?? ''). '<br>';
            });

            $table->editColumn('status', function ($row) {
                $status = '';
                if ($row->status == '0' || $row->status) {
                    $status = Video::STATUS[$row->status] ?? '';
                }
                if(Auth::user()->checkUserRole([1, 2, 7])){
                    $coverStatus = '';
                    if ($row->cover_status) {
                        $coverStatus = Video::COVERSTATUS[$row->cover_status] ?? '';
                    }
                    if($coverStatus){
                        $status .= '<br>('.$coverStatus.')';
                    }else{
                        $status .= '<br>('.Video::COVERSTATUS[1].')';
                    }
                }
                return $status;
            });

            $table->editColumn('cover_photo_html', function ($row) {
                if($row->cover_photo){
                    $cover_photo = $row->servers->play_domain . "/public" . $row->cover_photo;
                    return '<img src="' . $cover_photo . '" class="table-img clickable-img">';
                }else{
                    return '<img src="' . asset('picture/no-image.png') . '" class="table-img">';
                }
            });

            $table->editColumn('uploader', function ($row) {
                if ($row->uploader == '0') {
                    return "接口传入";
                }
                return $row->uploaderUser->username;
            });

            $table->editColumn('author_id', function ($row) {
                if ($row->author) {
                    return $row->author->name;
                }
                return "";
            });

            $table->editColumn('author', function ($row) {
                if ($row->uploader == '0') {
                    return "接口传入";
                }
                return $row->uploaderUser->username;
            });

            $table->editColumn('author_time', function ($row) {
                return $row->created_at;
            });

            $table->editColumn('approved', function ($row) {
                if(!Auth::user()->checkUserRole([5, 6])){
                    if ($row->uploader == '0') {
                        $uploader = "接口传入";
                    } else {
                        $uploader = $row->uploaderUser?->username;
                    }
    
                    $text = '<b>创建者 : </b>' . strip_tags($uploader) . '<br>' .
                        '<b>创建时间 : </b>' . strip_tags($row->created_at) . '<br>';
                    $first_approved_by = "";
                    if ($row->first_approved_by) {
                        $first_approved_by = $row->firstApprovedByUser->username;
                    }
    
                    if(Auth::user()->checkUserRole([1, 4])){
                        $assigned_to = "";
                        if ($row->assigned_to) {
                            $assigned_to = $row->assignedUser->username;
                        }
                        $text .= '<b>分配人 : </b>' . strip_tags($assigned_to) . '<br>' .
                            '<b>分配时间 : </b>' . strip_tags($row->assigned_at) . '<br>';
                        if($row->rereviewer_by && $row->status != 3){
                            $rereviewer_by = $row->reReviewUser->username;
                            $text .= '<b>重新审核人 : </b>' . strip_tags($rereviewer_by) . '<br>' .
                            '<b>重新审核时间 : </b>' . strip_tags($row->rereviewer_at) . '<br>';
                        }
                    }
    
                    if(Auth::user()->checkUserRole([1, 7])){
                        $cover_assigned_to = "";
                        if ($row->cover_assigned_to) {
                            $cover_assigned_to = $row->assignedCoverUser->username;
                        }
                        $text .= '<b>封面图分配人 : </b>' . strip_tags($cover_assigned_to) . '<br>' .
                            '<b>封面图分配时间 : </b>' . strip_tags($row->cover_assigned_at) . '<br>';
                    }
    
                    if($first_approved_by){
                        $text .= '<b>审核人 : </b>' . strip_tags($first_approved_by) . '<br>' .
                            '<b>审核时间 : </b>' . strip_tags($row->first_approved_at) . '<br>';
                    }
                    if ($row->reason && $row->status != 3) {
                        $text .= '<b>审核不通过/重新审核理由 : </b>' . strip_tags($row->reason) . '<br>';
                    }  
                    $text .= '<hr>';
                }else{
                    $text = "";
                }
                
                $text .= Video::getTableRowAction($row, $this->crudRoutePart);
                
                return $text;
            });

            $table->editColumn('video', function ($row) {
                if ($row->path) {
                    $path = $row->servers->play_domain . '/public' . $row->path;
                    return view('widget.videoItem', [
                        'video_link' => $path
                    ]);
                }
                return "";
            });

            $table->editColumn('tag', function ($row) {
                $tag_labels = [];
                foreach ($row->tags as $tags) {
                    $tag_labels[] = '<span class="label">' . strip_tags($tags->name) . '</span>';
                }
                return implode(' ', $tag_labels);
            });

            $table->editColumn('type', function ($row) {
                $type_labels = [];
                foreach ($row->types as $type) {
                    $type_labels[] = '<span class="label2">' . strip_tags($type->name) . '</span>';
                }
                return implode(' ', $type_labels);
            });

            $table->editColumn('actions_grid', function ($row) {
                $html = '<div id="dropdown' . $row->id . '" class="dropdown-content">';
                $html .= Video::getTableRowAction($row, $this->crudRoutePart, 0);
                $html .= '</div>';
                return $html;
            });

            $table->editColumn('cover_photo', function ($row) {
                if ($row->cover_photo) {
                    return $row->servers->play_domain . '/public' . $row->cover_photo;
                }
                return "";
            });

            $table->editColumn('cover_vertical', function ($row) {
                if ($row->cover_vertical) {
                    return $row->servers->play_domain . '/public' . $row->cover_vertical;
                }
                return "";
            });

            $table->editColumn('cover_vertical_html', function ($row) {
                if($row->cover_vertical){
                    $cover_photo = $row->servers->play_domain . "/public" . $row->cover_vertical;
                    return '<img src="' . $cover_photo . '" class="table-img-vertical clickable-img">';
                }else{
                    return '<img src="' . asset('picture/no-image.png') . '" class="table-img-vertical">';
                }
            });

            $table->editColumn('path', function ($row) {
                if ($row->path) {
                    return $row->servers->play_domain . '/public' . $row->path;
                }
                return "";
            });

            $table->rawColumns(['actions', 'details', 'cover_vertical_html', 'cover_photo_html', 'video', 'approved', 'tag', 'type', "actions_grid", "status"]);

            $redis = Redis::connection('default');
            $temp = $request->all();
            $temp['search_ori'] = $temp["search"]['value'] ?? '';
            unset($temp["_"],$temp["columns"],$temp["order"],$temp["draw"],$temp["search"],$temp["start"],$temp["length"]);
            $requestKey = 'video_datatable_count_' . md5(json_encode($temp));
            if ($redis->exists($requestKey)) {
                $filteredCount = $redis->get($requestKey);
            } else {
                $query = Video::search($request);
                if($temp['search_ori']){
                    $search = $temp['search_ori'];
                    $query->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(videos.id) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(videos.uid) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(videos.title) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(videos.source) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(videos.code) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(videos.code_others) LIKE ?', ["%{$search}%"]);
                    });
                }
                $filteredCount = $query->count();
                $redis->setex($requestKey, 600, $filteredCount); 
            }
           
            $requestData = [
                'success'  => $request->success,
                'reReview' => $request->reReview,
                'roles'    => Auth::user()->getCachedRoles()
            ];
            $requestKey = 'video_datatable_total_count' . md5(json_encode($requestData));
            if ($redis->exists($requestKey)) {
                $totalCount = $redis->get($requestKey);
            } else {
                $videoQuery = Video::query();
                if ($request->success && !Auth::user()->checkUserRole([7])) {
                    $videoQuery->where('status', 3);
                }elseif ($request->reReview && !Auth::user()->checkUserRole([7])) {
                    $videoQuery->where('status', 1);
                }else{
                    if (Auth::user()->checkUserRole([5, 6])) {
                        $videoQuery->where('status', 3);
                    }elseif (Auth::user()->checkUserRole([4])) {
                        $videoQuery->where('status', 1);
                    }
                }
                $totalCount = $videoQuery->count();
                $redis->setex($requestKey, 60 * 60 * 24, $totalCount);
            }

            return $table->setTotalRecords($totalCount)->setFilteredRecords($filteredCount)->make(true);
        }
        $resolution = Video::RESOLUTION_FILTER;
        $videoStatus = Video::STATUS;
        $project = Auth::user()->projects->first();
        if($project){
            if(Auth::user()->checkUserRole([5, 6])){
                if(!$project->enable_4k){
                    unset($resolution[1]);
                }
            }
            if(!Auth::user()->checkUserRole([1])){
                if(!$project->direct_cut){
                    unset($videoStatus[5]);
                }
            }
        }
        $filters = [
            'id' =>
            [
                'name' => 'id',
                'type' => 'text',
            ],
            'tag' => [
                'name' => '标签',
                'type' => 'select',
                'route' => route('tags.select'),
            ],
            'type' => [
                'name' => '分类',
                'type' => 'select',
                'route' => route('types.select'),
            ],
            'author' => [
                'name' => '作者',
                'type' => 'select',
                'route' => route('authors.select'),
            ],
            'resolution' => [
                'name' => '分辨率',
                'type' => $resolution,
            ],
            'source' => [
                'name' => '片商',
                'type' => 'select',
                'route' => route('source.select'),
            ],
            'website' => [
                'name' => '资源来源',
                'type' => 'select',
                'route' => route('website.select'),
            ],
            'project_types' => [
                'name' => '主题',
                'type' => 'select',
                'route' => route('projectType.showSelect'),
            ],
        ];

        if (!Auth::user()->checkUserRole([4, 5, 6, 7])) {
            $filters['approved_by'] = [
                'name' => '审核者',
                'type' => 'select',
                'route' => route('users.reviewer.select'),
            ];
        }

        if (!Auth::user()->checkUserRole([4, 5, 6, 7]) && !$request->success) {
            $filters['status'] = [
                'name' => '状态',
                'type' => $videoStatus
            ];
        }

        if (!Auth::user()->checkUserRole([3, 5, 6])) {
            $filters['uploader'] = [
                'name' => '创建者',
                'type' => 'select',
                'route' => route('users.uploader.select'),
                'init' => array(0 => [
                    'id' => 0,
                    'text' => "接口传入"
                ])
            ];
        }elseif (Auth::user()->checkUserRole([5, 6])){
            $project_uploader = User::active()->whereHas('role', function ($query) {
                $query->where('roles.id', 3);
            })->whereHas('projects', function ($query) use ($project) {
                $query->where('projects.id', $project->id);
            })->pluck('username', 'users.id')->toArray();
            $filters['uploader'] = [
                'name' => '创建者',
                'type' => $project_uploader,
            ];
        }

        if(Auth::user()->checkUserRole([1, 2])){
            $filters['assigned_to'] = [
                'name' => '被分配人',
                'type' => 'select',
                'route' => route('users.reviewer.select'),
                'init' => array(0 => [
                    'id' => 0,
                    'text' => "没分配"
                ])
            ];

            $filters['cover_assigned_to'] = [
                'name' => '封面图被分配人',
                'type' => 'select',
                'route' => route('users.coverer.select'),
                'init' => array(0 => [
                    'id' => 0,
                    'text' => "没分配"
                ])
            ];

            $filters['cover_status'] = [
                'name' => '封面图状态',
                'type' => Video::COVERSTATUS
            ];

            $filters['first_approved_at'] = [
                'name' => '审核',
                'type' => 'date2',
            ];

            $filters['created_at'] = [
                'name' => '创建时间',
                'type' => 'date2',
            ];
        }
       
        $dailyQuest = false;
        $extraQuest = false;
        if (!$request->success && !$request->reReview) {
            if(Auth::user()->checkUserRole([4, 7])){
                if(Auth::user()->is_daily_press){
                    $extraQuest = true;
                }else{
                    $dailyQuest = true;
                }
            }
        }

        if ($request->success) {
            $crudRoutePart = 'videosSuccess';
            $title = (Auth::user()->checkUserRole([1, 2]) ? "审核" : "任务") . '成功视频';
        } elseif ($request->reReview) {
            $crudRoutePart = 'videosRereview';
            $title = (Auth::user()->checkUserRole([1, 2]) ? "重新审核" : "任务失败") . '视频';
        }else{
            $crudRoutePart = $this->crudRoutePart;
            $title = (Auth::user()->checkUserRole([4]) ? "任务" : "") . $this->title;
        }

        $multiSelectButton = [];
        if (Auth::user()->checkUserRole([1, 5, 6])) {
            $multiSelectButton['pre-select'] = [
                'name' => "预选",
                'status' => 5,
                'statusNow' => Video::STATUS[3],
                'route' => route('videos.massChangeStatus'),
                'confirm' => 0
            ];
        }
        if (Auth::user()->checkUserRole([1, 2, 4])) {
            $multiSelectButton['approved'] = [
                'name' => '通过<i class="bx bx-check"></i>',
                'status' => 3,
                'statusNow' => Video::STATUS[1] . "," . Video::STATUS[2],
                'route' => route('videos.massChangeStatus')
            ];
        }

        if(!Auth::user()->checkUserRole([5, 6])){
            $action_name = '审核';
            $action_width = '280px';
        }else{
            $action_name = '行动';
            $action_width = '215px';
        }

        $columns = [
            "id" => ["name" => "ID", 'width' => '20px'],
            "details" => ["name" => '详情', 'sort' => 0, 'width' => '320px','className'=>'details'],
            "uid" => ["name" => "编码", 'visible' => 0],
            "title" => ["name" => '标题', 'visible' => 0],
            "cover_photo_html" => ["name" => '封面图', 'sort' => 0],
        ];

        $flag =false;
        if(Auth::user()->checkUserRole([1])){
            $flag = true;
        }else{
            $selected_projects = Config::getCachedConfig('cover_vertical_project_id');
            if($selected_projects){
                $selected_projects = explode(",",$selected_projects);
            }else{
                $selected_projects = [];
            }
    
            $user = Auth::user();
            $projects = $user->projects->first();
            if($projects?->id){
                if(in_array($projects?->id, $selected_projects)){
                    $flag = true;
                }
            }
        }

        if($flag){
            $columns["cover_vertical_html"] = ["name" => '竖图', 'sort' => 0];
        }
       
        $columns = array_merge($columns, [
            'video' => ["name" => '视频', 'sort' => 0, 'width' => '70px'],
            "status" => ["name" => '状态', 'width' => '40px', 'sort' => 0],
            "author_id" => ["name" => '作者', 'visible' => 0, 'sort' => 0],
            "uploader" => ["name" => '创建者', 'visible' => 0, 'sort' => 0],
            "cover_photo" => ["name" => '封面图路径', 'visible' => 0, 'sort' => 0],
            "cover_vertical" => ["name" => '竖图路径', 'visible' => 0, 'sort' => 0],
            "path" => ["name" => '路径', 'visible' => 0, 'sort' => 0],
            "source" => ["name" => '片商', 'visible' => 0],
            "code" => ["name" => '番号', 'visible' => 0],
            "code_others" => ["name" => '定制番号', 'visible' => 0],
            "author_time" => ["name" => '创建者', 'visible' => 0, 'sort' => 0],
            "author" => ["name" => '创建时间', 'visible' => 0, 'sort' => 0],
            "tag" => ["name" => '标签', 'visible' => 0, 'sort' => 0],
            "type" => ["name" => '分类', 'visible' => 0, 'sort' => 0],
            "actions_grid" => ["name" => '分类', 'visible' => 0, 'sort' => 0],
            'approved' => ["name" => $action_name, 'sort' => 0, 'width' => $action_width],
        ]);

        $content = view('index', [
            'title' => $title,
            'crudRoutePart' => $crudRoutePart,
            'columns' => $columns,
            'setting' => [
                'actions' => 0,
                'dailyQuest' => $dailyQuest,
                'extraQuest' => $extraQuest,
                'create' => 0,
                'video' => view('widget.video', ['script' => 0]),
                'grid' => 1,
                'pageLength' => 12,
                'lengthMenu' => "[ 12, 24, 52, 72, 100 ]",
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => $filters
                    ]
                ),
                'imageModal' => view('widget.imageModal', [
                    'script' => 0,
                ]),
                'rejectStatus' => view('widget.rejectModal', [
                    'script' => 0,
                    "modalBtnClass" => Video::REJECT_BTN . "," . Video::REREVIEW_BTN,
                    'crudRoutePart' => $this->crudRoutePart . '.changeStatusModal',
                    'value' => 4
                ]),
                'rejectStatusBtn' => Video::REJECT_BTN . "," . Video::REREVIEW_BTN,
                'multiSelect' => [
                    'button' => $multiSelectButton,
                ],
            ],
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user_id = Auth::user()->id;
        $serverIdArray = Ftp::where('user_id', $user_id)->pluck('server_id')->toArray();
        if (empty($serverIdArray)) {
            return back()->withErrors([
                'msg' => '用户没有ftp账号,不能创建视频',
            ]);
        }
        $randomString = bin2hex(random_bytes(4));
        $processId = getmypid();
        $columns = [
            'uid' => [
                'name' => '编码',
                'type' => 'text',
                'required' => 1,
                'readonly' => 1,
                'value' => uniqid($randomString . $processId)
            ],
            'title' => [
                'name' => '标题',
                'type' => 'text',
                'required' => 1
            ],
            'description' => [
                'name' => '描述',
                'type' => 'textarea',
                'required' => 1
            ],
            'types' => [
                'name' => '分类',
                'type' => 'select',
                'required' => 0,
                'route' => route('types.select')
            ],
            'tags' => [
                'name' => '标签',
                'type' =>  Tag::active()->pluck('name', 'id')->toArray(),
                'multiple' => 1,
                'required' => 0,
                'modal' => 1
            ],
            'source' => [
                'name' => '片商',
                'type' => 'text',
            ],
            'website' => [
                'name' => '资源来源',
                'type' => 'text',
            ],
            'code' => [
                'name' => '番号',
                'type' => 'text',
            ],
            'code_others' => [
                'name' => '定制番号',
                'type' => 'text',
                'condition' => [
                    'types' => Video::SHOWCODEOTHERS,
                ]
            ],
            'author_id' => [
                'name' => '作者',
                'type' => 'select',
                'required' => 0,
                'create' => 1,
                'route' => route('authors.select'),
                'pre' => array(0 => [
                    'id' => -1,
                    'text' => "创建作者"
                ])
            ],
            'path' => [
                'name' => '视频地址',
                'type' => 'video',
                'required' => 1,
                'placeholder' => 'video.mkv'
            ],
            'pre_cut_seconds' => [
                'name' => '片头(秒)',
                'type' => 'number',
            ],
            'post_cut_seconds' => [
                'name' => '片尾(秒)',
                'type' => 'number',
            ],
            'image'=>[
                'name' => '封面图',
                'type' => 'file',
                'setting' => [
                    'type' => 'image',
                    'tempUploadUrl' => route('tempUpload')
                ]
            ],
            'subtitle'=>[
                'name' => '字幕',
                'type' => 'file',
                'setting' => [
                    'accept' => '.srt,.ass',
                ]
            ],
            // 'server_id' => [
            //     'name' => '服务器',
            //     'type' => 'select',
            //     'route' => route('servers.select'),
            //     'required' => 1
            // ]
        ];

        $flag = false;
        if(Auth::user()->checkUserRole([1])){
            $flag = true;
        }else{
            $selected_projects = Config::getCachedConfig('cover_vertical_project_id');
            if($selected_projects){
                $selected_projects = explode(",",$selected_projects);
            }else{
                $selected_projects = [];
            }
    
            $user = Auth::user();
            $projects = $user->projects->first();
            if($projects?->id){
                if(in_array($projects?->id, $selected_projects)){
                    $flag = true;
                }
            }
        }
        
        if($flag){
            $columns["image_vertical"] = [
                'name' => '竖图',
                'type' => 'file',
                'setting' => [
                    'type' => 'image',
                    'tempUploadUrl' => route('tempUpload')
                ]
            ];
        }
        if(Auth::user()->checkUserRole([3])){
            $projects = Auth::user()->projects->first();
            if($projects?->direct_cut){
                $columns['prules'] = [
                    'name' => '规则',
                    'type' => ProjectRules::active()->where('project_id', $projects->id)->pluck('name', 'id')->toArray(),
                    'required' => 1,
                ];
                $columns['ptypes'] = [
                    'name' => '主题',
                    'type' => ProjectTypes::active()->where('project_id', $projects->id)->pluck('name', 'id')->toArray(),
                    'multiple' => 1
                ];
            }
        }
        $content = view('form', [
            'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => $columns,
        ]);

        return view('template', compact('content'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
        $validate = $request->validate([
            'uid' => ['required', Rule::unique('videos', 'uid')],
            'title' => ['required', Rule::unique('videos', 'title')],
            'description' => ['required'],
            'tags' => [],
            'types' => [],
            'code' => [],
            'pre_cut_seconds' => [],
            'post_cut_seconds' => [],
            'code_others' => ['nullable', Rule::unique('videos', 'code_others')],
            'path' => ['required'],
            'author_id' => [],
            'image' => [],
            'image_vertical' => [],
            // 'server_id' => ['required'],
            'source' => [],
            'subtitle' => [],
            'website' => []
        ], [
            'uid.required' => '编码不能为空',
            'uid.unique' => '编码已被使用',
            'code_others.unique' => '定制番号已被使用',
            'title.required' => '标题不能为空',
            'title.unique' => '标题已被使用',
            'description.required' => '描述不能为空',
            'path.required' => '视频地址不能为空',
            // 'server_id.required' => '服务器不能为空',
        ]);

        $uploadedFile = $validate['image'] ?? null;
        if ($uploadedFile) {
            unset($validate['image']);
        }

        $uploadedFileVertical = $validate['image_vertical'] ?? null;
        if ($uploadedFileVertical) {
            unset($validate['image_vertical']);
        }

        $subtitle = $request->file('subtitle');
        if(($validate['subtitle'] ?? '')){
            unset($validate['subtitle']);
        }

        if(($validate['types'] ?? '') == Video::SHOWCODEOTHERS && !($validate['author_id'] ?? '')){
            return back()->withInput()->withErrors([
                'msg' => '日本视频作者不能为空',
            ]);
        }

        $user_id = Auth::user()->id;
        $ftp = Ftp::where('user_id', $user_id)->first();
        if ($validate['path'][0] !== '/') {
            $validate['path'] = '/' . $validate['path'];
        }
        $validate['path'] = '/' . $ftp->nickname . $validate['path'];
        if ($validate['path']) {
            $extension = strtolower(pathinfo($validate['path'], PATHINFO_EXTENSION));

            if ($extension !== 'mp4') {
                return back()->withInput()->withErrors([
                    'msg' => '只能使用mp4格式视频',
                ]);
            }
        }

        $response = Video::checkValid($validate['path']);
        if (($response->code ?? 0) == 200) {
            if (isset($response->data)) {
                if (isset($response->data->size)) {
                    $validate['size'] = $response->data->size;
                }
                if (isset($response->data->resolution)) {
                    $validate['resolution'] = $response->data->resolution;
                    $validate['resolution_tag'] = Video::getResolutionTag($validate['resolution'] ?? "");
                }
                if (isset($response->data->md5)) {
                    $validate['md5'] = $response->data->md5;
                }
                if (isset($response->data->path)) {
                    $validate['path'] = $response->data->path;
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
            return back()->withInput()->withErrors([
                'msg' => '视频' . $error,
            ]);
        }
        try{
            $validate['server_id'] = Video::baseCheckLanDomain('https://resources.minggogogo.com',$validate['path']);
        } catch (\Exception $e) {
            return back()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
        $validate['uploader'] = Auth::user()->id;
        $validate['project_id'] = Auth::user()->projects->first()?->id ?? null;
        $validate['status'] = 1;
        $validate['cover_status'] = 1;
        if($validate['author_id'] ?? ''){
            if($validate['author_id'] == -1){
                if($request->get('create_author_id')){
                    $newAuthorName = $request->get('create_author_id');
                    $author = Author::where('name', $newAuthorName)->first();
                    if ($author) {
                        return back()->withInput()->withErrors([
                            'msg' => '新作者已被使用',
                        ]);
                    }
                    $newAuthor = Author::create([
                        'name' => $newAuthorName,
                        'status' => 1
                    ]);
                    $validate['author_id'] = $newAuthor->id;
                }else{
                    unset($validate['author_id']);
                }
            }
        }

        if(Auth::user()->checkUserRole([3])){
            $projects = Auth::user()->projects->first();
            if($projects?->direct_cut){
                $validate['status'] = 5;
                $prules = $request->get('prules', "");
                $ptypes = $request->get('ptypes',[]);
                if(!$prules){
                    return back()->withInput()->withErrors([
                        'msg' => '规则不能为空',
                    ]);
                }
            }
        }

        $video = Video::create($validate);
        if($subtitle){
            $fileName = time() . '_' . $subtitle->getClientOriginalName();
            $filePath = $subtitle->storeAs('public/subtitle/' . $video->id, $fileName);
            $filePath = str_replace("public/","",$filePath);
            $video->subtitle = '/storage/' . $filePath;
        }
        if ($uploadedFile) {
            $path ='public'. str_replace("/storage","",$uploadedFile);
            $file = Storage::path($path);
            $curlFile = curl_file_create($file);
            $extension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
            list($response,$coverNewPath) = Video::sendCover($validate['server_id'], $validate['path'], $curlFile, $video->id, $extension);
            if (!$response || !$coverNewPath) {
                return back()->withInput()->withErrors([
                    'msg' => '封面图'.$coverNewPath,
                ]);
            }
            Storage::delete($path);
            $video->cover_photo = $coverNewPath;
        }
        if($uploadedFileVertical){
            $pathVertical ='public'. str_replace("/storage","",$uploadedFileVertical);
            $fileVertical = Storage::path($pathVertical);
            $curlFileVertical = curl_file_create($fileVertical);
            $extensionVertical = pathinfo($uploadedFileVertical, PATHINFO_EXTENSION);
            list($responseVertical,$coverNewPathVertical) = Video::sendCoverVertical($validate['server_id'], $validate['path'], $curlFileVertical, $video->id, $extensionVertical);
            if (!$responseVertical || !$coverNewPathVertical) {
                return back()->withInput()->withErrors([
                    'msg' => '竖图'.$coverNewPathVertical,
                ]);
            }
            Storage::delete($pathVertical);
            $video->cover_vertical = $coverNewPathVertical;
        }
        $video->tags()->sync($validate['tags'] ?? []);
        $video->types()->sync($validate['types'] ?? []);
        $video->save();
        Video::processSaveLog($request->all(), $video, 1);

        if(Auth::user()->checkUserRole([3])){
            $projects = Auth::user()->projects->first();
            if($projects?->direct_cut){
                $videoChoose = VideoChoose::create([
                    'project_id' => $projects->id,
                    'created_by' => Auth::user()->id,
                    'created_at' => now(),
                    'video_id' => $video->id,
                    'status' => 1
                ]);
                $temp = new \stdClass();
                $temp->theme = $ptypes;
                $temp->rule = $prules;
                try{
                    $videoChoose = VideoChoose::cutStatus($temp, $videoChoose->id);
                } catch (\Exception $e) {
                    return back()->withErrors([
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '添加成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function generateCover(Request $request, string $id)
    {
        $validate = $request->validate([
            'temporary-image' => ['required'],
            'position' => ['required'],
            'borderSpace' => ['required'],
            'fontName' => ['required'],
            'fontSize' => ['required'],
            'fontColor' => ['required'],
            'lineSpace' => ['required'],
            'text1' => ['required'],
            'text2' => [],
            'text3' => [],
        ], [
            'temporary-image.required' => '封面图不能为空',
            'position.required' => '位置不能为空',
            'borderSpace.required' => '距离视频边框不能为空',
            'fontName.required' => '字体名称不能为空',
            'fontSize.required' => '字体大小不能为空',
            'fontColor.required' => '字体颜色不能为空',
            'lineSpace.required' => '字体每行间隔不能为空',
            'text1.required' => '文字1不能为空',
        ]);
        $video = Video::findOrFail($id);
        if (strpos($validate['temporary-image'], 'http') !== false || strpos($validate['temporary-image'], 'https') !== false) {
            $validate['inputFile'] = $validate['temporary-image'];
        } else {
            $validate['inputFile'] = asset($validate['temporary-image']);
        }
        unset($validate['temporary-image']);

        $validate['text'] = $validate['text1'];
        if($validate['text2'] ?? ''){
            $validate['text'] .= "\n" . $validate['text2'];
        }
        if($validate['text3'] ?? ''){
            $validate['text'] .= "\n" . $validate['text3'];
        }
        unset($validate['text1'], $validate['text2'], $validate['text3']);
        $validate['outputFile'] = Auth::user()->username . "/" . Helper::randomString(6) . "_" . time() .".png";

        $validate['position'] = (int)$validate['position'];
        $validate['borderSpace'] = (int)$validate['borderSpace'];
        $validate['fontSize'] = (int)$validate['fontSize'];
        $validate['lineSpace'] = (int)$validate['lineSpace'];

        $response = Helper::sendResourceRequest(
            url('image/build'),
            json_encode($validate),
            array('Content-Type: application/json'),
            'Generate Cover'
        );
        $response = json_decode($response);
        if (($response->code ?? 0) == 200) {
            return url('image/public/'.$validate['outputFile']);
        }else{
            return $response->msg ?? false;
        }
        return false;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $video = Video::with(['tags', 'types'])->findOrFail($id);
        if(Auth::user()->isCoverer()){
            $extra = view('generateCover', [
                'id' => $id,
                'crudRoutePart' => $this->crudRoutePart,
                'columns' => [
                    'cover_font' => [
                        'name' => '封面图添加字体',
                        'type' =>  'switch',
                        'required' => 1,
                        'value' => 0,
                        'setting' => [
                            'position' => [
                                'name' => '位置',
                                'type' =>  ProjectRules::LOGO,
                                'required' => 1,
                            ],
                            'borderSpace' => [
                                'name' => '距离视频边框',
                                'type' =>  'number',
                                'required' => 1,
                            ],
                            'fontName' => [
                                'name' => '字体名称',
                                'type' =>  ProjectRules::PICTUREFONTNAME,
                                'required' => 1,
                            ],
                            'fontSize' => [
                                'name' => '字体大小',
                                'type' =>  'number',
                                'required' => 1,
                            ],
                            'fontColor' => [
                                'name' => '字体颜色',
                                'type' =>  'color',
                                'required' => 1,
                            ],
                            'lineSpace' => [
                                'name' => '字体每行间隔',
                                'type' =>  'number',
                                'required' => 1,
                            ],
                            'text1' => [
                                'name' => '文字1',
                                'type' =>  'text',
                                'required' => 1,
                            ],
                            'text2' => [
                                'name' => '文字2',
                                'type' =>  'text',
                            ],
                            'text3' => [
                                'name' => '文字3',
                                'type' =>  'text',
                            ],
                        ]
                    ],
                ],
            ]);

            $content = view('form', [
                'extra' => $extra,
                'edit' => 1,
                'id' => $id,
                'title' => $this->title,
                'crudRoutePart' => $this->crudRoutePart,
                'buttons' => $this->buttons,
                'columns' => [
                    'path' => [
                        'name' => '视频地址',
                        'type' => 'video',
                        'value' => $video->servers->play_domain . '/public' . $video->path,
                        'show' => $video->path,
                        'required' => 1,
                        'readonly' => 1,
                        'placeholder' => 'video.mkv'
                    ],
                    'title' => [
                        'name' => '标题',
                        'type' => 'text',
                        'value' => $video->title,
                        'readonly' => 1,
                        'required' => 1
                    ],
                    'types' => [
                        'name' => '分类',
                        'type' => 'select',
                        'required' => 1,
                        'readonly' => 1,
                        'route' => route('types.select'),
                        'value' => $video->types->pluck('id')->toArray(),
                        'label' => $video->types->pluck('name')->toArray(),
                    ],
                    'tags' => [
                        'name' => '标签',
                        'type' =>  Tag::active()->pluck('name', 'id')->toArray(),
                        'multiple' => 1,
                        'value' => $video->tags->pluck('id')->toArray(),
                        'required' => 0,
                        'readonly' => 1,
                        'modal' => 1
                    ],
                    'image'=>[
                        'name' => '图片',
                        'type' => 'file',
                        'value' => [
                            'src' => $video->cover_photo ? $video->servers->play_domain . '/public' . $video->cover_photo : "",
                            'id' =>  $video->id
                        ],
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ],
                    ],
                ]
            ]);
        }else{
            if (Auth::user()->checkUserRole([3]) && $video->uploader != Auth::user()->id) {
                return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                    'msg' => '用户无权限修改别人的视频',
                ]);
            }
            
            $columns = [
                'uid' => [
                    'name' => '编码',
                    'type' => 'text',
                    'value' => $video->uid,
                    'required' => 1,
                    'readonly' => 1,
                ],
                'title' => [
                    'name' => '标题',
                    'type' => 'text',
                    'value' => $video->title,
                    'required' => 1
                ],
                'description' => [
                    'name' => '描述',
                    'type' => 'textarea',
                    'value' => $video->description,
                    'required' => 1
                ],
                'types' => [
                    'name' => '分类',
                    'type' => 'select',
                    'required' => 1,
                    'route' => route('types.select'),
                    'value' => $video->types->pluck('id')->toArray(),
                    'label' => $video->types->pluck('name')->toArray(),
                ],
                'tags' => [
                    'name' => '标签',
                    'type' =>  Tag::active()->pluck('name', 'id')->toArray(),
                    'multiple' => 1,
                    'value' => $video->tags->pluck('id')->toArray(),
                    'required' => 0,
                    'modal' => 1
                ],
                'others' => [
                    'name' => '其他',
                    'type' => 'json',
                    'value' => json_decode($video->others) ?? [],
                ],
                'source' => [
                    'name' => '片商',
                    'type' => 'text',
                    'value' => $video->source
                ],
                'website' => [
                    'name' => '资源来源',
                    'type' => 'text',
                    'value' => $video->website
                ],
                'code' => [
                    'name' => '番号',
                    'type' => 'text',
                    'value' => $video->code,
                ],
                'code_others' => [
                    'name' => '定制番号',
                    'type' => 'text',
                    'value' => $video->code_others,
                    'condition' => [
                        'types' => Video::SHOWCODEOTHERS,
                    ]
                ],
                'author_id' => [
                    'name' => '作者',
                    'type' => 'select',
                    'required' => 0,
                    'create' => 1,
                    'route' => route('authors.select'),
                    'value' => $video->author_id,
                    'label' => $video->authors?->name,
                    'pre' => array(0 => [
                        'id' => -1,
                        'text' => "创建作者"
                    ])
                ],
                'resolution' => [
                    'name' => '分辨率',
                    'type' => 'text',
                    'value' => $video->resolution,
                    'required' => 1,
                    'readonly' => 1,
                ],
                'size' => [
                    'name' => '大小',
                    'type' => 'text',
                    'value' => $video->size,
                    'required' => 1,
                    'readonly' => 1,
                ],
                'path' => [
                    'name' => '视频地址',
                    'type' => 'video',
                    'value' => $video->servers->play_domain . '/public' . $video->path,
                    'show' => $video->path,
                    'required' => 1,
                    'readonly' => 1,
                    'placeholder' => 'video.mkv'
                ],
                'pre_cut_seconds' => [
                    'name' => '片头(秒)',
                    'type' => 'number',
                    'value' => $video->pre_cut_seconds,
                ],
                'post_cut_seconds' => [
                    'name' => '片尾(秒)',
                    'type' => 'number',
                    'value' => $video->post_cut_seconds,
                ],
                'image'=>[
                    'name' => '图片',
                    'type' => 'file',
                    'value' => [
                        'src' => $video->cover_photo ? $video->servers->play_domain . '/public' . $video->cover_photo : "",
                        'id' =>  $video->id
                    ],
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ], 
                'subtitle'=>[
                    'name' => '字幕',
                    'type' => 'file',
                    'value' => $video->subtitle ? asset($video->subtitle): "",
                    'setting' => [
                        'accept' => '.srt,.ass',
                    ]
                ],
                // 'server_id' => [
                //     'name' => '服务器',
                //     'type' => 'select',
                //     'required' => 1,
                //     'readonly' => 1,
                //     'route' => route('servers.select'),
                //     'value' => $video->server_id,
                //     'label' => $video->servers->name . ' (' . $video->servers->ip . ')',
                // ],
            ];
            $flag = false;
            if(Auth::user()->checkUserRole([1])){
                $flag = true;
            }else{
                $selected_projects = Config::getCachedConfig('cover_vertical_project_id');
                if($selected_projects){
                    $selected_projects = explode(",",$selected_projects);
                }else{
                    $selected_projects = [];
                }
        
                $user = Auth::user();
                $projects = $user->projects->first();
                if($projects?->id){
                    if(in_array($projects?->id, $selected_projects)){
                        $flag = true;
                    }
                }
            }
            
            if($flag){
                $columns["image_vertical"] = [
                    'name' => '竖图',
                    'type' => 'file',
                    'value' => [
                        'src' => $video->cover_vertical ? $video->servers->play_domain . '/public' . $video->cover_vertical : "",
                        'id' =>  $video->id
                    ],
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ];
            }
            if(Auth::user()->checkUserRole([4])){
                unset($columns['uid'],$columns['server_id']);
            }
            if(Auth::user()->checkUserRole([1])){
                $columns['assigned_to'] = [
                    'name' => '被分配人',
                    'type' => 'select',
                    'route' => route('users.reviewer.select'),
                    'value' => $video->assigned_to,
                    'label' => $video->assignedUser?->username,
                ];

                $columns['cover_assigned_to'] = [
                    'name' => '封面图被分配人',
                    'type' => 'select',
                    'route' => route('users.coverer.select'),
                    'value' => $video->cover_assigned_to,
                    'label' => $video->assignedCoverUser?->username,
                ];
            }
            if(Auth::user()->checkUserRole([1, 2])){
                $columns['cover_status'] = [
                    'name' => '封面图状态',
                    'type' =>  Video::COVERSTATUS,
                    'value' => $video->cover_status,
                    'required' => 1,
                ];
            }
            $content = view('form', [
                'extra' => '',
                'edit' => 1,
                'id' => $id,
                'title' => $this->title,
                'crudRoutePart' => $this->crudRoutePart,
                'buttons' => $this->buttons,
                'columns' => $columns
            ]);
        }
        return view('template', compact('content'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
        $video = Video::findOrFail($id);
        $original = Video::getManyRelationModel($video);
        try{
            $server_id = Video::baseCheckLanDomain('https://resources.minggogogo.com',$video->path);
        } catch (\Exception $e) {
            return back()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
        if(Auth::user()->isCoverer()){
            $validate = $request->validate([
                'image' => [],
            ], []);

            $uploadedFile = $validate['image'] ?? null;
            if ($uploadedFile) {
                unset($validate['image']);
            }else{
                if(!$video->cover_photo){
                    return back()->withInput()->withErrors([
                        'msg' => '封面图不能为空',
                    ]);
                }
            }

            if ($uploadedFile) {
                $curlFile = '';
                $urlCheck = url('image/public/');
                if(strpos($uploadedFile, $urlCheck) !== false){
                    $imageData = file_get_contents($uploadedFile);
                    $fileName = time() . '_' . $video->id;
                    $path = storage_path('app/public/temp'). $fileName . '.png';
                    file_put_contents($path, $imageData);
                    $curlFile = curl_file_create($path);
                }else{
                    $needle = $video->servers->play_domain;
                    if ($needle && !str_contains($uploadedFile, $needle)) {
                        $path ='public'. str_replace("/storage","",$uploadedFile);
                        $file = Storage::path($path);
                        $curlFile = curl_file_create($file);
                    }
                }
                if($curlFile){
                    $extension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                    list($response,$coverNewPath) = Video::sendCover($server_id, $video->path, $curlFile, $video->id, $extension);
                    if (!$response || !$coverNewPath) {
                        return back()->withInput()->withErrors([
                            'msg' => '封面图'.$coverNewPath,
                        ]);
                    }
                    Storage::delete($path);
                    $validate['cover_photo'] = $coverNewPath;
                    $validate['cover_status'] = 2;
                    $validate['cover_changed_at'] = Carbon::now();
                    $video->update($validate);
                    Video::processSaveLog($request->all(), $video, 2, $original);
                }
            }
        }else{
            if (Auth::user()->checkUserRole([3]) && $video->uploader != Auth::user()->id) {
                return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                    'msg' => '用户无权限修改别人的视频',
                ]);
            }
            $validate = $request->validate([
                'title' => ['required', Rule::unique('videos', 'title')->ignore($id)],
                'description' => ['required'],
                'tags' => [],
                'types' => [],
                'code' => [],
                'pre_cut_seconds' => [],
                'post_cut_seconds' => [],
                'code_others' => ['nullable', Rule::unique('videos', 'code_others')->ignore($id)],
                'author_id' => [],
                'image' => [],
                'image_vertical' => [],
                'source' => [],
                'website' => [],
                'assigned_to' => [],
                'cover_assigned_to' => [],
                'cover_status' => [],
                'subtitle' => [],
            ], [
                'title.required' => '标题不能为空',
                'title.unique' => '标题已被使用',
                'description.required' => '描述不能为空',
                'code_others.unique' => '定制番号已被使用',
            ]);
            $uploadedFile = $validate['image'] ?? null;
            if ($uploadedFile) {
                unset($validate['image']);
            }else{
                if(!$video->cover_photo){
                    return back()->withInput()->withErrors([
                        'msg' => '封面图不能为空',
                    ]);
                }
            }

            $uploadedFileVertical = $validate['image_vertical'] ?? null;
            if ($uploadedFileVertical) {
                unset($validate['image_vertical']);
            }

            $subtitle = $request->file('subtitle');
            if(($validate['subtitle'] ?? '')){
                unset($validate['subtitle']);
            }

            if(($validate['types'] ?? '') == Video::SHOWCODEOTHERS && !($validate['author_id'] ?? '')){
                return back()->withInput()->withErrors([
                    'msg' => '日本视频作者不能为空',
                ]);
            }
            if($validate['author_id'] ?? ''){
                if($validate['author_id'] == -1){
                    if($request->get('create_author_id')){
                        $newAuthorName = $request->get('create_author_id');
                        $author = Author::where('name', $newAuthorName)->first();
                        if ($author) {
                            return back()->withInput()->withErrors([
                                'msg' => '新作者已被使用',
                            ]);
                        }
                        $newAuthor = Author::create([
                            'name' => $newAuthorName,
                            'status' => 1
                        ]);
                        $validate['author_id'] = $newAuthor->id;
                    }else{
                        unset($validate['author_id']);
                    }
                }
            }
            if($subtitle){
                $fileName = time() . '_' . $subtitle->getClientOriginalName();
                $filePath = $subtitle->storeAs('public/subtitle/' . $video->id, $fileName);
                $filePath = str_replace("public/","",$filePath);
                $video->subtitle = '/storage/' . $filePath;
            }
            if ($uploadedFile) {
                $needle = $video->servers->play_domain;
                if ($needle && !str_contains($uploadedFile, $needle)) {
                    $path ='public'. str_replace("/storage","",$uploadedFile);
                    $file = Storage::path($path);
                    $curlFile = curl_file_create($file);
                    $extension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
                    list($response,$coverNewPath) = Video::sendCover($server_id, $video->path, $curlFile, $video->id, $extension);
                    if (!$response || !$coverNewPath) {
                        return back()->withInput()->withErrors([
                            'msg' => '封面图'.$coverNewPath,
                        ]);
                    }
                    Storage::delete($path);
                    $validate['cover_photo'] = $coverNewPath;
                }
            }

            if($uploadedFileVertical){
                $needle = $video->servers->play_domain;
                if ($needle && !str_contains($uploadedFile, $needle)) {
                    $path ='public'. str_replace("/storage","",$uploadedFileVertical);
                    $file = Storage::path($path);
                    $curlFile = curl_file_create($file);
                    $extension = pathinfo($uploadedFileVertical, PATHINFO_EXTENSION);
                    list($response,$coverNewPath) = Video::sendCoverVertical($server_id, $video->path, $curlFile, $video->id, $extension);
                    if (!$response || !$coverNewPath) {
                        return back()->withInput()->withErrors([
                            'msg' => '竖图'.$coverNewPath,
                        ]);
                    }
                    Storage::delete($path);
                    $validate['cover_vertical'] = $coverNewPath;
                }
            }

            if(($validate['types'] ?? '') != Video::SHOWCODEOTHERS){
                $validate['code_others'] = '';
            }

            if($validate['assigned_to'] ?? ""){
                $validate['assigned_at'] = now();
            }

            if($validate['cover_assigned_to'] ?? ""){
                $validate['cover_assigned_at'] = now();
            }
            $validate['server_id'] = $server_id;
            $video->tags()->sync($validate['tags'] ?? []);
            $video->types()->sync($validate['types'] ?? []);
            $video->update($validate);
            Video::processSaveLog($request->all(), $video, 2, $original);
        }
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        $video = Video::findOrFail($id);
        if (Auth::user()->checkUserRole([3]) && $video->uploader != Auth::user()->id) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '用户无权限删除别人的视频',
            ]);
        }
        if($video->status!=1){
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '无法删除视频',
            ]);
        }
        $videoChoose = VideoChoose::where('video_id',$id)->first();
        if($videoChoose){
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '已有项目预选此视频,无法删除视频',
            ]);
        }
        foreach($video->videoTypes as $type){
            $type->delete();
        }
        foreach($video->videoTags as $tag){
            $tag->delete();
        }
        unset($video->videoTypes,$video->videoTags);
        $video->delete();
        return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function show(string $id)
    {
        $video = Video::findOrFail($id);
        $columns = [
            'id' => [
                'name' => 'ID',
                'type' => 'text',
                'value' => $video->id,
            ],
            'uid' => [
                'name' => '编码',
                'type' => 'text',
                'value' => $video->uid,
            ],
            'title' => [
                'name' => '标题',
                'type' => 'text',
                'value' => $video->title,
            ],
            'description' => [
                'name' => '描述',
                'type' => 'text',
                'value' => $video->description
            ],
            'tag' => [
                'name' => '标签',
                'type' =>  'multiple',
                'value' => $video->tags->pluck('name')->toArray(),
            ],
            'type' => [
                'name' => '分类',
                'type' =>  'multiple',
                'value' => $video->types->pluck('name')->toArray(),
            ],
            'status' => [
                'name' => '状态',
                'type' => 'text',
                'value' => Video::STATUS[$video->status],
            ],
            'resolution' => [
                'name' => '分辨率',
                'type' => 'text',
                'value' => $video->resolution,
            ],
            'size' => [
                'name' => '大小',
                'type' => 'text',
                'value' => $video->size,
            ],
            'source' => [
                'name' => '片商',
                'type' => 'text',
                'value' => $video->source,
            ],
            'code' => [
                'name' => '番号',
                'type' => 'text',
                'value' => $video->code,
            ],
            'code_others' => [
                'name' => '定制番号',
                'type' => 'text',
                'value' => $video->code_others,
            ],
            'author_id' => [
                'name' => '作者',
                'type' => 'text',
                'value' => $video->author ? $video->author->name : "",
            ],
            'path' => [
                'name' => '视频地址',
                'type' => 'video',
                'value' =>  $video->path ? $video->servers->play_domain . '/public' . $video->path : "",
            ],
            'md5' => [
                'name' => 'md5',
                'type' => 'text',
                'value' => $video->md5,
            ],
            'image' => [
                'name' => '封面图',
                'type' => 'image',
                'value' => $video->cover_photo ? $video->servers->play_domain . '/public' . $video->cover_photo : "",
            ],
            'image_vertical' => [
                'name' => '竖图',
                'type' => 'image',
                'value' => $video->cover_vertical ? $video->servers->play_domain . '/public' . $video->cover_vertical : "",
            ],
            'subtitle' => [
                'name' => '字幕',
                'type' => 'file',
                'value' => $video->subtitle ? asset($video->subtitle): "",
            ],
            'server_id' => [
                'name' => '服务器',
                'type' => 'text',
                'value' => $video->servers->name . ' (' . $video->servers->ip . ')',
            ],
            'first_approved_by' => [
                'name' => '审核人',
                'type' => 'text',
                'value' => $video->firstApprovedByUser->username ?? '',
            ],
            'first_approved_at' => [
                'name' => '审核时间',
                'type' => 'text',
                'value' => $video->first_approved_at,
            ],
            'reason' => [
                'name' => '不通过理由',
                'type' => 'text',
                'value' => $video->reason,
            ],
            'assigned_to' => [
                'name' => '被分配人',
                'type' => 'text',
                'value' => $video->assignedUser->username ?? '',
            ],
            'assigned_at' => [
                'name' => '被分配时间',
                'type' => 'text',
                'value' => $video->assigned_at,
            ],
            'rereviewer_by' => [
                'name' => '重新审核人',
                'type' => 'text',
                'value' =>  $video->reReviewUser->username ?? '',
            ],
            'rereviewer_at' => [
                'name' => '重新审核时间',
                'type' => 'text',
                'value' =>  $video->rereviewer_at,
            ],
            'cover_status' => [
                'name' => '封面图状态',
                'type' => 'text',
                'value' =>  Video::COVERSTATUS[$video->cover_status],
            ],
            'cover_assigned_to' => [
                'name' => '封面图被分配人',
                'type' => 'text',
                'value' => $video->assignedCoverUser->username ?? '',
            ],
            'cover_assigned_at' => [
                'name' => '封面图被分配时间',
                'type' => 'text',
                'value' => $video->cover_assigned_at,
            ],
            'cover_changed_at' => [
                'name' => '封面图更换时间',
                'type' => 'text',
                'value' => $video->cover_changed_at,
            ],
        ];
        $videoChooseArray = [];
        foreach($video->chooseProject as $videoChoose){
            $videoChooseArray[] = [
                'project' => $videoChoose->project->name ?? '',
                'choose_by'=> $videoChoose->user->username ?? '',
                'choose_at'=> $videoChoose->created_at ?? '',
            ];
        }
        if(!empty($videoChooseArray)){
            array_unshift($videoChooseArray,[
                'project' => '预选项目',
                'choose_by'=> '预选者',
                'choose_at'=> '预选时间',
            ]);
            $columns['video_choose'] = [
                'name' => '预选',
                'type' => 'table',
                'value' => $videoChooseArray,
            ];
        }

        $columns['others'] = [
            'name' => '其他',
            'type' => 'json',
            'value' => json_decode($video->others),
        ];

        $backButton = $this->buttons;
        if (Auth::user()->checkUserRole([1, 5, 6])) {
            $backButton .= view('widget.backButton', [
                'title' => '预选区',
                'crudRoutePart' => 'videoChoose',
            ]);
        }

        if(Auth::user()->checkUserRole([4])){
            unset($columns['uid'],$columns['server_id']);
        }
        
        return view('view', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'backButton' => $backButton,
            'button' => '',
            'columns' => $columns
        ]);
    }

    public function changeStatusModal(Request $request)
    {
        try {
            $id = $request->get('id');
            $reason = $request->get('reason');
            $status = $request->get('status');
            $video = Video::findOrFail($id);
            $video = Video::changeStatus($video, $status);
            $video->reason = $reason;
            $video->save();
            Video::processSaveLog([], $video, 2, []);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $video = Video::findOrFail($id);
            $status = $request->get('status');
            $video = Video::changeStatus($video, $status);
            Video::processSaveLog([], $video, 2, []);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function massChangeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
            $id_array = json_decode($request->get('multi-id'), true);
            $status = $request->get('multi-status');
            foreach ($id_array as $video_id) {
                $video = Video::findOrFail($video_id);
                $video = Video::changeStatus($video, $status);
                Video::processSaveLog([], $video, 2, []);
            }
            DB::commit();
            return back()->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function extraQuest(){
        $user = Auth::user();
        if(!$user->isReviewer() && !$user->isCoverer()){
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '只有审核手/图片手能获取任务',
            ]);
        }
        if(!$user->is_daily_press){
            return back()->withErrors([
                'msg' => '用户还没获取每日任务',
            ]);
        }
        if($user->isReviewer()){
            $assignedVideo = Video::where('status', 1)->where('assigned_to', $user->id)->first();
        }else{
            $assignedVideo = Video::where('cover_status', '!=', 2)->where('cover_assigned_to', $user->id)->first();
        }
        if($assignedVideo){
            return back()->withErrors([
                'msg' => '用户还有未完成任务，查看任务列表以及任务失败页面',
            ]);
        }
        $video = Video::assign($user->extra_quest,$user->id);
        if($video == 0){
            return back()->withErrors([
                'msg' => '没视频分配',
            ]);
        }
        $user->is_extra_press = 1;
        $user->save();
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '获取任务成功');
    }

    public function dailyQuest(){
        $user = Auth::user();
        if(!$user->isReviewer() && !$user->isCoverer()){
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '只有审核手/图片手能获取任务',
            ]);
        }
        if($user->isReviewer()){
            $assignedVideo = Video::where('status', 1)->where('assigned_to', $user->id)->first();
        }else{
            $assignedVideo = Video::where('cover_status', '!=', 2)->where('cover_assigned_to', $user->id)->first();
        }
        if($assignedVideo){
            return back()->withErrors([
                'msg' => '用户还有未完成任务，查看任务列表以及任务失败页面',
            ]);
        }
        $video = Video::assign($user->daily_quest,$user->id);
        if($video == 0){
            return back()->withErrors([
                'msg' => '没视频分配',
            ]);
        }
        $user->is_daily_press = 1;
        $user->save();
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '获取任务成功');
    }

    public function sourceSelect(Request $request)
    {
        $search = $request->get('q', '');
        $query = Video::query()
            ->select('source as id', 'source as text')
            ->whereNotNull('source')
            ->where('source',"!=",'')
            ->distinct();

        if (!empty($search)) {
            $query->where(DB::raw('LOWER(source)'), 'LIKE', '%' . strtolower($search) . '%');
        }

        $results = $query->simplePaginate(10);
        $items = $results->items();

        if ($request->page == 1 && $request->has('pre')) {
            $decodedPre = json_decode(htmlspecialchars_decode($request->get('pre')));
            if (is_array($decodedPre)) {
                foreach (array_reverse($decodedPre) as $prevalue) {
                    array_unshift($items, [
                        'id' => $prevalue->id,
                        'text' => $prevalue->text,
                    ]);
                }
            }
        }
        
        return response()->json([
            'results' => $items,
            'pagination' => [
                'more' => $results->hasMorePages()
            ]
        ], 200);
    }

    public function websiteSelect(Request $request)
    {
        $search = $request->get('q', '');
        $query = Video::query()
            ->select('website as id', 'website as text')
            ->whereNotNull('website')
            ->where('website',"!=",'')
            ->distinct();

        if (!empty($search)) {
            $query->where(DB::raw('LOWER(website)'), 'LIKE', '%' . strtolower($search) . '%');
        }

        $results = $query->simplePaginate(10);
        $items = $results->items();

        if ($request->page == 1 && $request->has('pre')) {
            $decodedPre = json_decode(htmlspecialchars_decode($request->get('pre')));
            if (is_array($decodedPre)) {
                foreach (array_reverse($decodedPre) as $prevalue) {
                    array_unshift($items, [
                        'id' => $prevalue->id,
                        'text' => $prevalue->text,
                    ]);
                }
            }
        }
        
        return response()->json([
            'results' => $items,
            'pagination' => [
                'more' => $results->hasMorePages()
            ]
        ], 200);
    }
}
