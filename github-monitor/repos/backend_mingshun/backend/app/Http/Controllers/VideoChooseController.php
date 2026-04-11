<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\ProjectTypes;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoChoose;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 上传手只能看到，编辑及删除自己的视频
 */
class VideoChooseController extends Controller
{
    public function __construct()
    {
        $this->init(VideoChoose::class);
        parent::__construct();
    }

    public function historyIndex(Request $request)
    {
        $request->merge(['history' => 1]);
        return $this->index($request);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $projects = $user->projects->first();
        if (!Auth::user()->checkUserRole([1, 2])) {
            if (!$projects) {
                return back()->withErrors([
                    'msg' => '用户没项目，无法进入预选区',
                ]);
            }
        }
        if ($request->ajax()) {
            $query = VideoChoose::with(['projectRule','video.servers', 'user', 'project'])->search($request)->select(sprintf('%s.*', (new VideoChoose())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) use ($request){
                return VideoChoose::getTableRowAction($row, $this->crudRoutePart, 1, $request->history);
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    if (Auth::user()->checkUserRole([1, 2])) {
                        return VideoChoose::STATUS[$row->status];
                    }else{
                        if($row->status == 9 || $row->status == 11){
                            return VideoChoose::STATUS[2];
                        }else if($row->status == 10 || $row->status == 12 || $row->status == 13){
                            return VideoChoose::STATUS[3];
                        }else{
                            return VideoChoose::STATUS[$row->status];
                        }
                    }
                }
                return '';
            });

            $table->editColumn('cover_photo', function ($row) {
                if($row->video){
                    if ($row->video->cover_photo) {
                        return $row->video->servers->play_domain . '/public' . $row->video->cover_photo;
                    }
                }
                return "";
            });

            $table->editColumn('path', function ($row) {
                if($row->video){
                    if ($row->video->path) {
                        return $row->video->servers->play_domain . '/public' . $row->video->path;
                    }
                }
                return "";
            });

            $table->editColumn('author', function ($row) {
                $user = $row->user;
                $created_by = "";
                if ($user) {
                    $created_by = $user->username ?? '';
                }
                return $created_by;
            });

            $table->editColumn('author_time', function ($row) {
                return $row->created_at;
            });

            $table->editColumn('tag', function ($row) {
                $tag_labels = [];
                if($row->video){
                    foreach ($row->video->tags as $tags) {
                        $tag_labels[] = '<span class="label filter-click" key="tag" filter="'. $tags->id .'">' . strip_tags($tags->name) . '</span>';
                    }
                }
                return implode(' ', $tag_labels);
            });

            $table->editColumn('type', function ($row) {
                $type_labels = [];
                if($row->video){
                    foreach ($row->video->types as $type) {
                        $type_labels[] = '<span class="label2 filter-click" key="type" filter="'. $type->id .'">' . strip_tags($type->name) . '</span>';
                    }
                }
                return implode(' ', $type_labels);
            });

            $table->editColumn('details', function ($row) {
                if($row->video){
                    $tag_labels = [];
                    foreach ($row->video->tags as $tags) {
                        $tag_labels[] = '<span class="label filter-click" key="tag" filter="'. $tags->id .'">' . strip_tags($tags->name) . '</span>';
                    }
                    $type_labels = [];
                    foreach ($row->video->types as $type) {
                        $type_labels[] = '<span class="label2 filter-click" key="type" filter="'. $type->id .'">' . strip_tags($type->name) . '</span>';
                    }
                    $project_type_labels = [];
                    foreach ($row->types as $type) {
                        $project_type_labels[] = '<span class="label3">' . strip_tags($type->name) . '</span>';
                    }
                    $ruleName='';
                    if ($row->project_rule_id) {
                        if($row->projectRule){
                            $ruleName=$row->projectRule->name;
                        }
                    }
                    if ($row->video->path) {
                        $path = $row->video->servers->play_domain . '/public' . $row->video->path;
                    } else {
                        $path = "";
                    }
                    $author = "";
                    if ($row->video->author) {
                        $author = $row->video->author->name;
                    }
                    return '<b>uid : </b>' . strip_tags($row->video->uid) . '<br>' .
                        '<b>视频id : </b>' . strip_tags($row->video_id) . '<br>' .
                        '<b>标题 : </b><a target="_blank" href="' . $path . '">' . strip_tags($row->video->title) . '<br></a>' .
                        '<b>作者 : </b>' . strip_tags($author) . '<br>' .
                        '<b>分辨率 : </b>' . strip_tags($row->video->resolution) . '<br>' .
                        '<b>大小 : </b>' . strip_tags($row->video->size) . '<br>' .
                        '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
                        '<b>分类 : </b>' . implode(' ', $type_labels). '<br>' .
                        '<b>规则 : </b>' . strip_tags($ruleName). '<br>' .
                        '<b>主题 : </b>' . implode(' ', $project_type_labels) . '<br>' .
                        '<b>片商 : </b>' . strip_tags($row->video->source) . '<br>' .
                        '<b>字幕 : </b>' . ($row->video->subtitle ? "有" : "没有"). '<br>';
                }
                return '';
            });

            $table->editColumn('cover_photo_html', function ($row) {
                if($row->video->cover_photo){
                    $cover_photo = $row->video->servers->play_domain . '/public' . $row->video->cover_photo;
                    return '<img src="' . $cover_photo . '" class="table-img clickable-img">';
                }else{
                    return '<img src="' . asset('picture/no-image.png') . '" class="table-img">';
                }
            });

            $table->editColumn('created', function ($row) {
                $user = $row->user;
                $created_by = "";
                if ($user) {
                    $created_by = $user->username ?? '';
                }
                $temp = '<b>预选者 : </b>' . strip_tags($created_by) . '<br>' .
                    '<b>预选时间 : </b>' . strip_tags($row->created_at);
                if(in_array($row->status,[2,3,4])){
                    $temp .=   '<br><b>切片时间 : </b>' . strip_tags($row->cut_at);
                }
                if (in_array($row->status,[2,3]) && $row->cut_callback_msg) {
                    if(Auth::user()->checkUserRole([1, 2])){
                        $cut_callback_msg = $row->cut_callback_msg;
                    }else{
                        $cut_callback_msg = explode("--", $row->cut_callback_msg);
                        $cut_callback_msg = $cut_callback_msg[0];
                    }
                    $temp .=   '<br><b>切片返回信息 : </b>' . strip_tags($cut_callback_msg);
                }
                if(in_array($row->status,[4,5,8])){
                    $temp .=   '<br><b>同步时间 : </b>' . strip_tags($row->sync_at);
                }
                if ($row->status == 4 && $row->sync_callback_msg) {
                    $temp .=   '<br><b>同步返回信息 : </b>' . strip_tags($row->sync_callback_msg);
                }
                if (in_array($row->status,[9, 10, 11, 12, 13]) ){
                     $temp .=   '<br><b>生成字幕时间 : </b>' . strip_tags($row->ai_at);
                }
                if (in_array($row->status,[10, 11, 13]) && $row->subtitle_callback_msg) {
                    $temp .=   '<br><b>生成字幕返回信息 : </b>' . strip_tags($row->subtitle_callback_msg);
                }
                if (in_array($row->status,[12]) && $row->sync_callback_msg) {
                    $temp .=   '<br><b>生成字幕返回信息 : </b>' . strip_tags($row->sync_callback_msg);
                }
                if(in_array($row->status,[7,8])){
                    $temp .=   '<br><b>发送时间 : </b>' . strip_tags($row->callback_at);
                }
                if ($row->status == 8 && $row->send_callback_msg) {
                    $temp .=   '<br><b>发送返回信息 : </b>' . strip_tags($row->send_callback_msg);
                }
                return $temp;
            });

            $table->editColumn('project.name', function ($row) {
                $project = $row->project;
                if ($project) {
                    return $project->name ?? '';
                }
                return '';
            });

            $table->editColumn('video_link', function ($row) {
                if($row->video){
                    if ($row->video->path) {
                        $path = $row->video->servers->play_domain . '/public' . $row->video->path;
                        return view('widget.videoItem', [
                            'video_link' => $path
                        ]);
                    }
                }
                return "";
            });

            $table->editColumn('actions_grid', function ($row) use ($request){
                $html = '<div id="dropdown' . $row->id . '" class="dropdown-content">';
                $html .= VideoChoose::getTableRowAction($row, $this->crudRoutePart, 0, $request->history);
                $html .= '</div>';
                return $html;
            });

            $table->editColumn('title', function ($row) {
                if($row->video){
                    return $row->video->title;
                }
                return '';
            });

            $table->editColumn('uid', function ($row) {
                if($row->video){
                    return $row->video->uid;
                }
                return '';
            });

            $table->rawColumns(['actions', 'details', 'cover_photo_html', 'video', "created", "tag", "type", "actions_grid"]);

            $redis = Redis::connection('default');
            $temp = $request->all();
            $temp['search_ori'] = $temp["search"]['value'] ?? '';
            unset($temp["_"],$temp["columns"],$temp["order"],$temp["draw"],$temp["search"],$temp["start"],$temp["length"]);
            $requestKey = 'video_datatable_count_' . md5(json_encode($temp));
            if ($redis->exists($requestKey)) {
                $filteredCount = $redis->get($requestKey);
            } else {
                $query = VideoChoose::search($request);
                if($temp['search_ori']){
                    $search = $temp['search_ori'];
                    $query->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(video_chooses.id) LIKE ?', ["%{$search}%"])
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->select(DB::raw(1))
                                ->from('videos')
                                ->whereColumn('video_chooses.video_id', 'videos.id')
                                ->whereRaw('LOWER(videos.uid) LIKE ?', ["%{$search}%"]);
                        })
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->select(DB::raw(1))
                                ->from('videos')
                                ->whereColumn('video_chooses.video_id', 'videos.id')
                                ->whereRaw('LOWER(videos.title) LIKE ?', ["%{$search}%"]);
                        });
                    });
                }
                $filteredCount = $query->count();
                $redis->setex($requestKey, 600, $filteredCount); 
            }

            $requestKey = 'video_choose_datatable_total_count';
            if ($redis->exists($requestKey)) {
                $totalCount = $redis->get($requestKey);
            } else {
                $totalCount = VideoChoose::count();
                $redis->setex($requestKey, 600, $totalCount); 
            }

            return $table->setTotalRecords($totalCount)->setFilteredRecords($filteredCount)->make(true);
        }


        $filters = [
            'id' =>
            [
                'name' => 'id',
                'type' => 'text',
            ],
            'video_id' =>
            [
                'name' => '视频id',
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
            'source' =>
            [
                'name' => '片商',
                'type' => 'select',
                'route' => route('source.select'),
            ],
        ];

        if($request->history){
            $status_array = VideoChoose::STATUS;
            unset($status_array[1]);
            if (!Auth::user()->checkUserRole([1, 2])) {
                unset($status_array[9],$status_array[10],$status_array[11],$status_array[12],$status_array[13]);
            }
            $filters['status'] = [
                'name' => '状态',
                'type' => $status_array,
            ];
        }

        if (Auth::user()->checkUserRole([1, 2])) {
            $filters['created_by'] = [
                'name' => '预选者',
                'type' => 'select',
                'route' => route('users.select'),
            ];
            $filters['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'route' => route('projects.select'),
            ];
        } else {
            $filters['created_by'] = [
                'name' => '预选者',
                'type' => $projects->users->pluck('username', 'id')->toArray(),
            ];
        }

        $ruleValue = [];
        if(!Auth::user()->checkUserRole([1, 2])){
            $ruleValue = ProjectRules::project($projects->id)->active()->pluck('name', 'id')->toArray();
            $themeValue = ProjectTypes::active()->project($projects->id)->pluck('name', 'id')->toArray();
        }else{
            $ruleArray = ProjectRules::active()->get()->toArray();
            foreach($ruleArray as $rule){
                $ruleValue[$rule['id']] = [
                    'value' => $rule['name'],
                    'extra' => $rule['project_id'],
                ];
            }

            $themeArray = ProjectTypes::active()->get()->toArray();
            foreach($themeArray as $theme){
                $themeValue[$theme['id']] = [
                    'value' => $theme['name'],
                    'extra' => $theme['project_id'],
                ];
            }
        }
       
        $columns = [
            "id" => ["name" => "ID", 'width' => '20px'],
            "details" => ["name" => '详情', 'sort' => 0, 'width' => '300px','className'=>'details'],
            "video.uid" => ["name" => "编码", 'visible' => 0],
            "video.title" => ["name" => '标题', 'visible' => 0],
            "cover_photo_html" => ["name" => '封面图', 'sort' => 0],
            'video_link' => ["name" => '视频', 'sort' => 0],
            "status" => ["name" => '状态', 'sort' => 0],
            "created" => ["name" => '预选者', 'sort' => 0, 'width' => '300px','className'=>'details'],
            "project.name" => ["name" => '项目', 'sort' => 0],
            "cover_photo" => ["name" => '封面图路径', 'visible' => 0, 'sort' => 0],
            "path" => ["name" => '路径', 'visible' => 0, 'sort' => 0],
            "author_time" => ["name" => '预选者', 'visible' => 0, 'sort' => 0],
            "author" => ["name" => '预选时间', 'visible' => 0, 'sort' => 0],
            "tag" => ["name" => '标签', 'visible' => 0, 'sort' => 0],
            "type" => ["name" => '分类', 'visible' => 0, 'sort' => 0],
            "actions_grid" => ["name" => '分类', 'visible' => 0, 'sort' => 0],
        ];

        if (($projects->name ?? "")) {
            unset($columns['project.name']);
        }
        if ($request->history) {
            $title = '视频预选历史（' . ($projects->name ?? '全') . '）';
            $crudRoutePart = "videoChooseHistory";
        } else {
            $title = '视频预选区（' . ($projects->name ?? '全') . '）';
            $crudRoutePart = 'videoChoose';
        }
        $multiSelectButton = [];
        $multiSelectModalButton = [];
        if (Auth::user()->checkUserRole([1, 6])) {
            if($request->history){
                if (Auth::user()->checkUserRole([1])) {
                    $multiSelectButton['resync'] = [
                        'name' => "重新同步",
                        'status' => 2,
                        'statusNow' => VideoChoose::STATUS[4],
                        'route' => route('videoChoose.massChangeStatus')
                    ];
                    $multiSelectButton['rechoose'] = [
                        'name' => "重新预选",
                        'status' => -1,
                        'statusNow' => VideoChoose::STATUS[2]. "," . VideoChoose::STATUS[3]. "," . VideoChoose::STATUS[4]. "," . VideoChoose::STATUS[5] . "," . VideoChoose::STATUS[7]. "," . VideoChoose::STATUS[8]. "," . VideoChoose::STATUS[10]. "," . VideoChoose::STATUS[12]. "," . VideoChoose::STATUS[13],
                        'route' => route('videoChoose.massChangeStatus')
                    ];
                    $multiSelectButton['rechoose'] = [
                        'name' => "重新发送字幕",
                        'status' => 9,
                        'statusNow' => VideoChoose::STATUS[12],
                        'route' => route('videoChoose.massChangeStatus')
                    ];
                    $multiSelectButton['rechoose'] = [
                        'name' => "重新生成字幕",
                        'status' => 10,
                        'statusNow' => VideoChoose::STATUS[10],
                        'route' => route('videoChoose.massChangeStatus')
                    ];
                }
                $multiSelectButton['recut'] = [
                    'name' => "重新切片",
                    'status' => 2,
                    'statusNow' => VideoChoose::STATUS[3],
                    'route' => route('videoChoose.massChangeStatus')
                ];
                $filters['cut_at'] = [
                    'name' => '切片时间',
                    'type' => 'date2',
                ];
                $multiSelectButton['resend'] = [
                    'name' => "重新发送",
                    'status' => 7,
                    'statusNow' => VideoChoose::STATUS[8],
                    'route' => route('videoChoose.massChangeStatus')
                ];
            }else{
                $multiSelectModalButton['cut'] = [
                    'name' => "切片",
                    'status' => 2,
                    'statusNow' => VideoChoose::STATUS[1],
                    'modal' => 'cut-modal',
                    'modalID' => 'chg-cut-modal-id',
                    'modalStatus' => 'chg-cut-modal-status',
                    'class' => ''
                ];
                $multiSelectButton['unchoose'] = [
                    'name' => "不预选",
                    'status' => 0,
                    'statusNow' => VideoChoose::STATUS[1],
                    'route' => route('videoChoose.massChangeStatus')
                ];
            }
        }
        if ($request->history) {
            if(!Auth::user()->checkUserRole([1, 2])){
                $project_types = ProjectTypes::project($projects->id)->pluck('name', 'id')->toArray();
                $filters['project_types'] = [
                    'name' => '主题',
                    'type' => $project_types,
                ];
            }else{
                $filters['project_types'] = [
                    'name' => '主题',
                    'type' => 'select',
                    'route' => route('projectType.select'),
                ];
            }
        }
        if(Auth::user()->checkUserRole([3])){
            $filters = [];
        }
        $content = view('index', [
            'title' => $title,
            'crudRoutePart' => $crudRoutePart,
            'columns' =>  $columns,
            'setting' => [
                'create' => 0,
                'video' => view('widget.video', ['script' => 0]),
                'grid' => 1,
                'pageLength' => 12,
                'lengthMenu' =>  "[ 12, 24, 52, 72, 100 ]",
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => $filters
                    ]
                ),
                'imageModal' => view('widget.imageModal', [
                    'script' => 0,
                ]),
                'cutStatus' => view('widget.cutModal', [
                    "modalBtnClass" => VideoChoose::CUT_BTN,
                    'crudRoutePart' => $this->crudRoutePart . '.cutStatus',
                    'value' => 2,
                    "ruleValue" => $ruleValue,
                    "themeValue" => $themeValue,
                ]),
                'cutStatusBtn' => VideoChoose::CUT_BTN,
                'rejectStatus' => view('widget.rejectModal', [
                    'script' => 0,
                    "modalBtnClass" => Video::REREVIEW_BTN,
                    'crudRoutePart' => $this->crudRoutePart . '.changeStatusModal',
                    'value' => 4
                ]),
                'rejectStatusBtn' => Video::REREVIEW_BTN,
                'multiSelectModal' => [
                    'button' => $multiSelectModalButton,
                ],
                'multiSelect' => [
                    'button' => $multiSelectButton,
                ],
            ],
        ]);

        return view('template', compact('content'));
    }

     /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $videoChoose = VideoChoose::findOrFail($id);
        $video = $videoChoose->video;
        if (Auth::user()->id == 3 && $video->uploader != Auth::user()->id) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '用户无权限修改别人的视频',
            ]);
        }
        $tag_labels = [];
        foreach ($video->tags as $tag) {
            $tag_labels[] = sprintf('<span class="label">%s</span>', $tag->name);
        }
        $type_labels = [];
        foreach ($video->types as $type) {
            $type_labels[] = '<span class="label2">' . $type->name . '</span>';
        }
        if ($video->path) {
            $path = $video->servers->play_domain . '/public' . $video->path;
        } else {
            $path = "";
        }
        $author = "";
        if ($video->author) {
            $author = $video->author->name;
        }
        $details = '<b>uid : </b>' . $video->uid . '<br>' .
            '<b>标题 : </b><a target="_blank" href="' . $path . '">' . $video->title . '<br></a>' .
            '<b>作者 : </b>' . $author . '<br>' .
            '<b>分辨率 : </b>' . $video->resolution . '<br>' .
            '<b>大小 : </b>' . $video->size . '<br>' .
            '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
            '<b>分类 : </b>' . implode(' ', $type_labels). '<br>' .
            '<b>片商 : </b>' . $video->source;
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'types' => [
                    'name' => '主题',
                    'type' =>  ProjectTypes::active()->project($videoChoose->project_id)->pluck('name', 'id')->toArray(),
                    'multiple' => 1,
                    'required' => 1,
                    'value' => $videoChoose->types->pluck('id')->toArray(),
                ],
                'details' => [
                    'name' => '详情',
                    'type' => 'html',
                    'value' => $details,
                    'readonly' => 1,
                ],
                'path' => [
                    'name' => '视频地址',
                    'type' => 'video',
                    'value' => $video->servers->play_domain . '/public' . $video->path,
                    'show' => $video->path,
                    'readonly' => 1,
                    'placeholder' => 'video.mkv'
                ],
                'image'=>[
                    'name' => '图片',
                    'type' => 'file',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => [
                        'src' => $video->cover_photo ? $video->servers->play_domain . '/public' . $video->cover_photo : "",
                        'id' =>  $video->id
                    ],
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
            ]
        ]);
        return view('template', compact('content'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $videoChoose = VideoChoose::findOrFail($id);
        $original = VideoChoose::getManyRelationModel($videoChoose);
        $validate = $request->validate([
            'types' => [],
        ], [
            'types.required' => '主题不能为空',
        ]);
        $types = [];
        foreach (($request->theme ?? []) as $typeId) {
            $types[$typeId] = [
                'video_id'   => $videoChoose->video_id,
                'video_chooses_id' => $videoChoose->id,
            ];
        }
        $videoChoose->types()->sync($types);
        VideoChoose::processSaveLog($request->all(), $videoChoose, 2, $original);
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '编辑成功');
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $videoChoose = VideoChoose::find($id);
            $status = $request->get('status');
            $videoChoose = VideoChoose::chageStatus($videoChoose, $status);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
           return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function massChangeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
            $id_array = json_decode($request->get('multi-id'), true);
            $status = $request->get('multi-status');
            foreach ($id_array as $videoChoose_id) {
                $videoChoose = VideoChoose::find($videoChoose_id);
                $videoChoose = VideoChoose::chageStatus($videoChoose, $status);
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

    public function cutStatus(Request $request)
    {
        try {
            $ids = json_decode($request->get('id'), true);
            if(gettype($ids) == 'array'){
                $project_id = VideoChoose::whereIn('id' , $ids)->pluck('project_id')->unique();
                if(count($project_id) > 1){
                    throw new \Exception('不能多选不同的项目');
                }
                foreach($ids as $id){
                    VideoChoose::cutStatus($request, $id);
                }
            }else{  
                VideoChoose::cutStatus($request, $ids);
            }
            $request->replace(['id' => $request->get('id')]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function show(string $id)
    {
        $videoChoose = VideoChoose::findOrFail($id);
        $video = $videoChoose->video;
        $tag_labels = [];
        foreach ($video->tags as $tag) {
            $tag_labels[] = sprintf('<span class="label">%s</span>', $tag->name);
        }
        $type_labels = [];
        foreach ($video->types as $type) {
            $type_labels[] = '<span class="label2">' . $type->name . '</span>';
        }
        $author = "";
        if ($video->author) {
            $author = $video->author->name;
        }
        $details = '<b>uid : </b>' . $video->uid . '<br>' .
            '<b>标题 : </b><a target="_blank" href="' . route('videos.show', $video->id) . '">' . $video->title . '<br></a>' .
            '<b>作者 : </b>' . $author . '<br>' .
            '<b>分辨率 : </b>' . $video->resolution . '<br>' .
            '<b>大小 : </b>' . $video->size . '<br>' .
            '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
            '<b>分类 : </b>' . implode(' ', $type_labels). '<br>' .
            '<b>片商 : </b>' . $video->source;

            if (Auth::user()->checkUserRole([1, 2])) {
                $status = VideoChoose::STATUS[$videoChoose->status];
            }else{
                if($videoChoose->status == 9 || $videoChoose->status == 11){
                    $status = VideoChoose::STATUS[2];
                }else if($videoChoose->status == 10 || $videoChoose->status == 12 || $videoChoose->status == 13){
                    $status = VideoChoose::STATUS[3];
                }else{
                    $status = VideoChoose::STATUS[$videoChoose->status];
                }
            }
        $columns = [
            'details' => [
                'name' => '详情',
                'type' => 'html',
                'value' => $details,
            ],
            'type' => [
                'name' => '主题',
                'type' =>  'multiple',
                'value' => $videoChoose->types->pluck('name')->toArray(),
            ],
            'project' => [
                'name' => '项目',
                'type' => 'text',
                'value' => $videoChoose->project->name,
            ],
            'created_at' => [
                'name' => '预选时间',
                'type' => 'text',
                'value' => $videoChoose->created_at,
            ],
            'created_by' => [
                'name' => '预选者',
                'type' => 'text',
                'value' => $videoChoose->user->username,
            ],
            'status' => [
                'name' => '状态',
                'type' => 'text',
                'value' => $status,
            ],
            'recut_time' => [
                'name' => '重新切片次数',
                'type' => 'text',
                'value' => $videoChoose->recut_time,
            ],
            'project_rule' => [
                'name' => '切片规则',
                'type' => 'text',
                'value' => $videoChoose->project_rule_id?$videoChoose->project_rules . ' (' . $videoChoose->project_rule_id  . ')' : '',
            ],
            'server' => [
                'name' => '切片资源服务器',
                'type' => 'text',
                'value' => $videoChoose->server,
            ],
            'cut_callback_msg' => [
                'name' => '切片返回信息',
                'type' => 'text',
                'value' => $videoChoose->cut_callback_msg,
            ],
            'cut_callback_success_msg' => [
                'name' => '切片成功返回信息',
                'type' => 'text',
                'value' => $videoChoose->cut_callback_success_msg,
            ],
            'cut_at' => [
                'name' => '切片时间',
                'type' => 'text',
                'value' => $videoChoose->cut_at,
            ],
            'sync_callback_msg' => [
                'name' => '同步返回信息',
                'type' => 'text',
                'value' => $videoChoose->sync_callback_msg,
            ],
            'sync_at' => [
                'name' => '同步时间',
                'type' => 'text',
                'value' => $videoChoose->sync_at,
            ],
            'send_callback_msg' => [
                'name' => '发送返回信息',
                'type' => 'text',
                'value' => $videoChoose->send_callback_msg,
            ],
            'callback_at' => [
                'name' => '发送时间',
                'type' => 'text',
                'value' => $videoChoose->callback_at,
            ],
            'ai_at' => [
                'name' => '生成字幕时间',
                'type' => 'text',
                'value' => $videoChoose->ai_at,
            ],
            'subtitle_callback_msg' => [
                'name' => '生成字幕返回信息',
                'type' => 'text',
                'value' => $videoChoose->subtitle_callback_msg,
            ],
        ];

        $backButton = view('widget.backButton', [
            'title' => '预选区',
            'crudRoutePart' => 'videoChoose',
        ]);

        $backButton .= view('widget.backButton', [
            'title' => '预选历史',
            'crudRoutePart' => 'videoChooseHistory',
        ]);
        
        return view('view', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'backButton' => $backButton,
            'button' => '',
            'columns' => $columns
        ]);
    }

    public function callback(string $id)
    {
        $videoChoose = VideoChoose::find($id);
        if(!$videoChoose){
            return back()->withErrors([
                'msg' => '无法找到预选视频',
            ]);
        }

        $flag = false;
        if(Auth::user()->isSuperAdmin()){
            $flag = true;
        }elseif(Auth::user()->projects->pluck('id')->toArray()){
            if (in_array($videoChoose->project_id, Auth::user()->projects->pluck('id')->toArray())){
                $flag = true;
            }
        }

        if(!$flag){
            return back()->withErrors([
                'msg' => '没权限读取回调',
            ]);
        }
        
        if($videoChoose->status != 7 && $videoChoose->status != 8){
            return back()->withErrors([
                'msg' => '预选视频没回调',
            ]);
        }
        $data = VideoChoose::getCallbackMessage($videoChoose);
        $rule = ProjectRules::find($videoChoose->project_rule_id);
        $link = '';
        if($rule){
            $link = $rule->callback_url;
        }  
        $columns = [
            'callback_link' => [
                'name' => '回调链接',
                'type' => 'text',
                'value' => $link,
            ],
            'callback_ip' => [
                'name' => '回调返回ip',
                'type' => 'text',
                'value' => $videoChoose->send_callback_ip,
            ],
            'callback_msg' => [
                'name' => '回调返回信息',
                'type' => 'text',
                'value' => $videoChoose->send_callback_msg,
            ],
            'original_json' => [
                'name' => '原回调json',
                'type' => 'text',
                'value' => json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ],
            'beautify_json' => [
                'name' => '回调json',
                'type' => 'html',
                'value' => VideoChoose::printJsonTree($data),
            ],
        ];

        $backButton = view('widget.backButton', [
            'title' => '预选区',
            'crudRoutePart' => 'videoChoose',
        ]);

        $backButton .= view('widget.backButton', [
            'title' => '预选历史',
            'crudRoutePart' => 'videoChooseHistory',
        ]);
        
        return view('view', [
            'title' => $this->title . "回调",
            'crudRoutePart' => $this->crudRoutePart,
            'backButton' => $backButton,
            'button' => '',
            'columns' => $columns
        ]);
    }

    public static function cut(){
        if(Auth::user()->id !=1){
            return back()->withErrors([
                'msg' => '没权限',
            ]);
        }
        $videoChooses = VideoChoose::with(['video'])->where('status',2)->get();
        $temp = [];
        foreach($videoChooses as $videoChoose){
            $temp[] = $videoChoose->video->uid . "__" . $videoChoose->id;
        }

        return json_encode($temp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    public function changeStatusModal(Request $request)
    {
        try {
            $id = $request->get('id');
            $reason = $request->get('reason');
            $status = $request->get('status');
            $videoChoose = VideoChoose::findOrFail($id);
            $video = $videoChoose->video;
            $video = Video::changeStatus($video, $status);
            $video->reason = $reason;
            $video->save();
            $allVideoChooses = VideoChoose::where('video_id', $videoChoose->video_id)->get();
            foreach($allVideoChooses as $allVideoChoose){
                $allVideoChoose->status = 6;
                $allVideoChoose->save();
            }
            Video::processSaveLog([], $video, 2, []);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
