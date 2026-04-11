<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Config;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\SubtitleLanguage;
use App\Models\Video;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectRulesController extends Controller
{
    public function __construct()
    {
        $this->init(ProjectRules::class);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $projects = $user->projects->first();
        if($user->checkUserRole([3])){
            if(!$projects->first()?->direct_cut){
                return back()->withErrors([
                    'msg' => '用户无权限，请咨询主管',
                ]);
            }
        }
        if ($request->ajax()) {
            $query = ProjectRules::search($request)->select(sprintf('%s.*', (new ProjectRules())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                if(Auth::user()->checkUserRole([3])){
                    $edit = 0;
                    $delete = 0;
                }else{
                    $edit = 1;
                    $delete = 1;
                }
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => $edit,
                    'delete' => $delete,
                    'isButton' => 1
                ]);
            });

            $table->editColumn('project_id', function ($row) {
                if ($row->projects) {
                    return $row->projects->name;
                }
                return '';
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return view('widget.statusForm', [
                        'crudRoutePart' => $this->crudRoutePart,
                        'selection' => ProjectRules::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->rawColumns(['actions', 'project', 'status']);

            return $table->make(true);
        }

        $column = [
            "id" => ["name" => "ID"],
            "name" => ["name" => "名字"],
        ];
        $filters = [
            'id' =>
            [
                'name' => 'id',
                'type' => 'text',
            ],
            'status' => [
                'name' => '状态',
                'type' => ProjectRules::STATUS,
            ],
        ];
        if (!$user->isSuperAdmin()) {
            $project_name = $projects->name;
        }else{
            $project_name = '全';
            $column['project_id'] = [
                'name' => '项目',
            ];
            $filters['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'route' => route('projects.select'),
            ];
        }
        $create = 1;
        if(Auth::user()->checkUserRole([3])){
            $filters = [];
            $create = 0;
        }else{
            $column['status'] = ["name" => "状态"];
            $column['created_at'] = ["name" => "创建时间"];
            $column['updated_at'] = ["name" => "更新时间"];
        }
        $content = view('index', [
            'title' => $this->title. '（' . $project_name . '）',
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => $column,
            'setting' => [
                'create' => $create,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => $filters
                    ]
                ),
            ],
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $column = [
            'name' => [
                'name' => '规则名',
                'type' => 'text',
                'required' => 1
            ],
            'head_enable' => [
                'name' => '视频片头',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'head_video' => [
                        'name' => '视频',
                        'type' => 'file',
                        'required' => 1,
                        'setting' => [
                            'type' => 'video',
                            'tempUploadUrl' => route('tempUploadVideo')
                        ]
                    ],
                ]
            ],
            'logo_enable' => [
                'name' => 'Logo',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'logo_image' => [
                        'name' => '图片',
                        'type' => 'file',
                        'required' => 1,
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ]
                    ],
                    'logo_position' => [
                        'name' => '位置',
                        'type' =>  ProjectRules::LOGO,
                        'required' => 1,
                    ],
                    'logo_padding' => [
                        'name' => '距离视频边框',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                     'logo_scale' => [
                        'name' => '缩放比例',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                ]
            ],
             'font_enable' => [
                'name' => 'Font',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'font_text' => [
                        'name' => '内容',
                        'type' =>  'text',
                        'required' => 1,
                    ],
                    'font_color' => [
                        'name' => '颜色',
                        'type' =>  'color',
                        'required' => 1,
                    ],
                    'font_size' => [
                        'name' => '大小(px)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                    'font_position' => [
                        'name' => '位置',
                        'type' =>  ProjectRules::FONT,
                        'required' => 1,
                    ],
                    'font_interval' => [
                        'name' => '文字滚动时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                    'font_scroll' => [
                        'name' => '滚动间隔时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                    'font_border' => [
                        'name' => '边框',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                    'font_shadow' => [
                        'name' => '阴影',
                        'type' =>  'switch',
                        'required' => 1,
                    ],
                    'font_space' => [
                        'name' => '屏幕间距',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                ]
            ],
            'ad_enable' => [
                'name' => '文字广告',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'ad_image' => [
                        'name' => '图片',
                        'type' => 'file',
                        'required' => 1,
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ]
                    ],
                    'ad_start' => [
                        'name' => '开始显示位置',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                ]
            ],
            'm3u8_enable' => [
                'name' => 'M3u8',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'm3u8_encrypt' => [
                        'name' => '是否加密',
                        'type' =>  'switch',
                        'required' => 1,
                    ],
                    'm3u8_interval' => [
                        'name' => '间隔时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 10
                    ],
                    'm3u8_bitrate' => [
                        'name' => '比特率(k)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                    'm3u8_fps' => [
                        'name' => '帧数',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 0
                    ],
                ]
            ],
            'preview_enable' => [
                'name' => '预览视频',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'preview_interval' => [
                        'name' => '间隔时间(秒)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 10
                    ],
                     'preview_skip' => [
                        'name' => '跳过片头(秒)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 10
                    ],
                ]
            ],
            'webp_enable' => [
                'name' => 'Webp',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'webp_start' => [
                        'name' => '从几秒开始',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 5
                    ],
                    'webp_length' => [
                        'name' => '截取一次的长度',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 1
                    ],
                    'webp_interval' => [
                        'name' => '截取间隔',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 1
                    ],
                    'reminder' => [
                        'name' => '提醒',
                        'type' => 'html',
                        'value' => '截取间隔设置0的话,自动计算间隔时间'
                    ],
                    'webp_count' => [
                        'name' => '截取多少张',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 10
                    ],
                ]
            ],
            'skip_enable' => [
                'name' => 'Skip',
                'type' =>  'switch',
                'required' => 1,
                'value' => 1,
                'setting' => [
                    'skip_head' => [
                        'name' => '去掉片头',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 30
                    ],
                    'skip_back' => [
                        'name' => '去掉片尾',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 30
                    ],
                ]
            ],
            // 'generate_subtitle' => [
            //     'name' => '字幕生成',
            //     'type' =>  'switch',
            //     'required' => 1,
            //     'value' => 0,
            //     'setting' => [
            //         'subtitle_languages' => [
            //             'name' => '字幕生成语言',
            //             'type' => SubtitleLanguage::active()->pluck('name', 'label')->toArray(),
            //             'multiple' => 1
            //         ],
            //     ]
            // ],
            'callback_url'=> [
                'name' => 'Api回调地址',
                'type' =>  'text',
                'required' => 1,
            ],
            'resolution_option'=> [
                'name' => '分辨率',
                'type' =>  ProjectRules::RESOLUTION,
                'required' => 1,
            ],
            'status' => [
                'name' => '状态',
                'type' =>  ProjectRules::STATUS,
                'required' => 1,
                'value' => 1
            ],
        ];
        $user = Auth::user();
        $projects = $user->projects->first();
        if ($user->isSuperAdmin()) {
            $column['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'required' => 1,
                'route' => route('projects.select')
            ];
        }
        $content = view('form', [
                'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => $column,
             
        ]);

        return view('template', compact('content'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $projects = $user->projects->first();
            if ($user->isSuperAdmin()) {
                $project_id = $request->get('project_id', '');
            }else{
                $project_id = $projects->id;
            }
            $validate = $request->validate([
                'name' => ['required', Rule::unique('project_rules', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })],
                'callback_url' => ['required'],
                'project_id' => [],
                'status' => ['required'],
                'resolution_option' => ['required'],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'callback_url.required' => 'Api回调地址不能为空',
                'status.required' => '状态不能为空',
                'resolution_option.required' => '分辨率不能为空',
            ]);
            $validate['project_id'] = $project_id;
            $projectRules = ProjectRules::create($validate);
            $id = $projectRules->id;
            $validate2['head_enable'] = $request->get('head_enable',0);
            if($validate2['head_enable']){
                if(!$request->head_video){
                    return back()->withInput()->withErrors([
                        'msg' => '视频片头里的视频不能为空',
                    ]);
                }
                $temp_image = str_replace("/storage","",$request->head_video);
                $image_name = str_replace("/storage/temp/","",$request->head_video);
                $head_video = 'projectRule/'. $id . "/" . $image_name;
                Storage::move('public'.$temp_image, 'public/'. $head_video);
                $validate2['head_video'] = '/storage/' . $head_video;
                $validate2['head_enable'] = 1;
            }else{
                $validate2['head_video'] = '';
            }

            $validate2['logo_enable'] = $request->get('logo_enable',0);
            if( $validate2['logo_enable']){
                if(!$request->logo_image){
                    return back()->withInput()->withErrors([
                        'msg' => 'Logo里的图片不能为空',
                    ]);
                }
                $response = Video::checkValid(asset($request->logo_image), 1, 1);
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
                    return back()->withInput()->withErrors([
                        'msg' => 'Logo图' . $error,
                    ]);
                }
                $temp_image = str_replace("/storage","",$request->logo_image);
                $image_name = str_replace("/storage/temp/","",$request->logo_image);
                $logo_image = 'projectRule/'. $id . "/" . $image_name;
                Storage::move('public'.$temp_image, 'public/'. $logo_image);
                $validate2['logo_image'] = '/storage/' . $logo_image;
                $validate2['logo_enable'] = 1;
            }else{
                $validate2['logo_image'] = '';
            }
            $validate2['logo_position'] = $request->get('logo_position',0);
            $validate2['logo_padding'] = $request->get('logo_padding',0);
            $validate2['logo_scale'] = $request->get('logo_scale',0); 

            $validate2['font_enable'] = $request->get('font_enable',0);
            if($validate2['font_enable']){
                $validate2['font_enable'] = 1;
            }
            $validate2['font_text'] = $request->get('font_text','');
            $validate2['font_color'] = $request->get('font_color','');
            $validate2['font_size'] = $request->get('font_size',0);
            $validate2['font_position'] = $request->get('font_position',0);
            $validate2['font_interval'] = $request->get('font_interval',0);
            $validate2['font_scroll'] = $request->get('font_scroll',0);
            $validate2['font_border'] = $request->get('font_border',0);
            $validate2['font_shadow'] = $request->get('font_shadow',0);
            $validate2['font_space'] = $request->get('font_space',0);

            $validate2['ad_enable'] = $request->get('ad_enable',0);
            if($validate2['ad_enable']){
                if(!$request->ad_image){
                    return back()->withInput()->withErrors([
                        'msg' => '文字广告里的图片不能为空',
                    ]);
                }
                $response = Video::checkValid(asset($request->ad_image), 1, 1);
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
                    return back()->withInput()->withErrors([
                        'msg' => '文字广告图' . $error,
                    ]);
                }
                $temp_image = str_replace("/storage","",$request->ad_image);
                $image_name = str_replace("/storage/temp/","",$request->ad_image);
                $ad_image = 'projectRule/'. $id . "/" . $image_name;
                Storage::move('public'.$temp_image, 'public/'. $ad_image);
                $validate2['ad_image'] = '/storage/' . $ad_image;
                $validate2['ad_enable'] = 1;
            }else{
                $validate2['ad_image'] = '';
            }
            $validate2['ad_start'] = $request->get('ad_start',0);

            $validate2['m3u8_enable'] = $request->get('m3u8_enable',0);
            if( $validate2['m3u8_enable']){
                $validate2['m3u8_enable'] = 1;
            }
            $validate2['m3u8_encrypt'] = $request->get('m3u8_encrypt',0);
            $validate2['m3u8_interval'] = $request->get('m3u8_interval',10);
            $validate2['m3u8_bitrate'] = $request->get('m3u8_bitrate',0);
            $validate2['m3u8_fps'] = $request->get('m3u8_fps',0);

            $validate2['preview_enable'] = $request->get('preview_enable',0);
            if($validate2['preview_enable']){
                $validate2['preview_enable'] = 1;
            }
            $validate2['preview_interval'] = $request->get('preview_interval',10);
            $validate2['preview_skip'] = $request->get('preview_skip',10);

            $validate2['webp_enable'] = $request->get('webp_enable',0);
            if($validate2['webp_enable']){
                $validate2['webp_enable'] = 1;
            }
            $validate2['webp_start'] = $request->get('webp_start',5);
            $validate2['webp_interval'] = $request->get('webp_interval',1);
            $validate2['webp_count'] = $request->get('webp_count',1);
            $validate2['webp_length'] = $request->get('webp_length',10);

            $validate2['skip_enable'] = $request->get('skip_enable',0);
            if($validate2['skip_enable']){
                $validate2['skip_enable'] = 1;
            }
            $validate2['skip_head'] = $request->get('skip_head',30);
            $validate2['skip_back'] = $request->get('skip_back',30);

            $validate2['generate_subtitle'] = $request->get('generate_subtitle',0);
            // if($validate2['generate_subtitle']){
            //     $validate2['generate_subtitle'] = 1;
            // }
            $subtitleLanguage = $request->get('subtitle_languages','');
            $validate2['subtitle_languages'] = $subtitleLanguage ? json_encode($subtitleLanguage ) : '';

            $projectRules->update($validate2);
            DB::commit();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '添加成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $projectRules = ProjectRules::findOrFail($id);
        $column = [
            'name' => [
                'name' => '规则名',
                'type' => 'text',
                'required' => 1,
                'value' => $projectRules->name
            ],
            'head_enable' => [
                'name' => '视频片头',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->head_enable,
                'setting' => [
                    'head_video' => [
                        'name' => '视频',
                        'type' => 'file',
                        'required' => 1,
                        'value' => [
                            'id' => $projectRules->id,
                            'src' => $projectRules->head_video,
                        ],
                        'setting' => [
                            'type' => 'video',
                            'tempUploadUrl' => route('tempUploadVideo')
                        ]
                    ],
                ]
            ],
            'logo_enable' => [
                'name' => 'Logo',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->logo_enable,
                'setting' => [
                    'logo_image' => [
                        'name' => '图片',
                        'type' => 'file',
                        'required' => 1,
                        'value' => [
                            'id' => $projectRules->id,
                            'src' => $projectRules->logo_image,
                        ],
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ]
                    ],
                    'logo_position' => [
                        'name' => '位置',
                        'type' =>  ProjectRules::LOGO,
                        'required' => 1,
                        'value' => $projectRules->logo_position,
                    ],
                    'logo_padding' => [
                        'name' => '距离视频边框',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->logo_padding,
                    ],
                     'logo_scale' => [
                        'name' => '缩放比例',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->logo_scale,
                    ],
                ]
            ],
             'font_enable' => [
                'name' => 'Font',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->font_enable,
                'setting' => [
                    'font_text' => [
                        'name' => '内容',
                        'type' =>  'text',
                        'required' => 1,
                        'value' => $projectRules->font_text,
                    ],
                    'font_color' => [
                        'name' => '颜色',
                        'type' =>  'color',
                        'required' => 1,
                        'value' => $projectRules->font_color,
                    ],
                    'font_size' => [
                        'name' => '大小(px)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->font_size,
                    ],
                    'font_position' => [
                        'name' => '位置',
                        'type' =>  ProjectRules::FONT,
                        'required' => 1,
                        'value' => $projectRules->font_position,
                    ],
                    'font_interval' => [
                        'name' => '文字滚动时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->font_interval,
                    ],
                    'font_scroll' => [
                        'name' => '滚动间隔时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->font_scroll,
                    ],
                    'font_border' => [
                        'name' => '边框',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->font_border,
                    ],
                    'font_shadow' => [
                        'name' => '阴影',
                        'type' =>  'switch',
                        'required' => 1,
                        'value' => $projectRules->font_shadow,
                    ],
                    'font_space' => [
                        'name' => '屏幕间距',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->font_space,
                    ],
                ]
            ],
            'ad_enable' => [
                'name' => '文字广告',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->ad_enable,
                'setting' => [
                    'ad_image' => [
                        'name' => '图片',
                        'type' => 'file',
                        'required' => 1,
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ],
                        'value' => [
                            'id' => $projectRules->id,
                            'src' => $projectRules->ad_image,
                        ],
                    ],
                    'ad_start' => [
                        'name' => '开始显示位置',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->ad_start,
                    ],
                ]
            ],
            'm3u8_enable' => [
                'name' => 'M3u8',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->m3u8_enable,
                'setting' => [
                    'm3u8_encrypt' => [
                        'name' => '是否加密',
                        'type' =>  'switch',
                        'required' => 1,
                        'value' => $projectRules->m3u8_encrypt,
                    ],
                    'm3u8_interval' => [
                        'name' => '间隔时间',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->m3u8_interval,
                    ],
                    'm3u8_bitrate' => [
                        'name' => '比特率(k)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->m3u8_bitrate,
                    ],
                    'm3u8_fps' => [
                        'name' => '帧数',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->m3u8_fps,
                    ],
                ]
            ],
            'preview_enable' => [
                'name' => '预览视频',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->preview_enable,
                'setting' => [
                    'preview_interval' => [
                        'name' => '间隔时间(秒)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->preview_interval,
                    ],
                     'preview_skip' => [
                        'name' => '跳过片头(秒)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->preview_skip,
                    ],
                ]
            ],
            'webp_enable' => [
                'name' => 'Webp',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->webp_enable,
                'setting' => [
                    'webp_start' => [
                        'name' => '从几秒开始',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->webp_start,
                    ],
                    'webp_length' => [
                        'name' => '截取一次的长度',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->webp_length,
                    ],
                    'webp_interval' => [
                        'name' => '截取间隔',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->webp_interval,
                    ],
                    'reminder' => [
                        'name' => '提醒',
                        'type' => 'html',
                        'value' => '截取间隔设置0的话,自动计算间隔时间'
                    ],
                    'webp_count' => [
                        'name' => '截取多少张',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->webp_count,
                    ],
                ]
            ],
            'skip_enable' => [
                'name' => 'Skip',
                'type' =>  'switch',
                'required' => 1,
                'value' => $projectRules->skip_enable,
                'setting' => [
                    'skip_head' => [
                        'name' => '去掉片头',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->skip_head,
                    ],
                    'skip_back' => [
                        'name' => '去掉片尾',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $projectRules->skip_back,
                    ],
                ]
            ],
            // 'generate_subtitle' => [
            //     'name' => '字幕生成',
            //     'type' =>  'switch',
            //     'required' => 1,
            //     'value' => $projectRules->generate_subtitle,
            //     'setting' => [
            //         'subtitle_languages' => [
            //             'name' => '字幕生成语言',
            //             'type' => SubtitleLanguage::active()->pluck('name', 'label')->toArray(),
            //             'multiple' => 1,
            //             'value' => json_decode($projectRules->subtitle_languages),
            //         ],
            //     ]
            // ],
            'callback_url'=> [
                'name' => 'Api回调地址',
                'type' =>  'text',
                'required' => 1,
                'value' => $projectRules->callback_url,
            ],
            'resolution_option'=> [
                'name' => '分辨率',
                'type' =>  ProjectRules::RESOLUTION,
                'required' => 1,
                'value' => $projectRules->resolution_option,
            ],
            'status' => [
                'name' => '状态',
                'type' =>  ProjectRules::STATUS,
                'required' => 1,
                'value' => $projectRules->status,
            ],
        ];
        $user = Auth::user();
        $projects = $user->projects->first();
        if ($user->isSuperAdmin()) {
            $column['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'required' => 1,
                'route' => route('projects.select'),
                'value' => $projectRules->project_id,
                'label' => $projectRules->projects?->name,
            ];
        }
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $projectRules->id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => $column
        ]);

        return view('template', compact('content'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            DB::beginTransaction();
            $projectRules = ProjectRules::findOrFail($id);
            $user = Auth::user();
            $projects = $user->projects->first();
            if ($user->isSuperAdmin()) {
                $project_id = $request->get('project_id', '');
            }else{
                $project_id = $projects->id;
            }
            $validate2 = $request->validate([
                'name' => ['required', Rule::unique('project_rules', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })->ignore($id)],
                'callback_url' => ['required'],
                'project_id' => [],
                'status' => ['required'],
                'resolution_option' => ['required'],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'callback_url.required' => 'Api回调地址不能为空',
                'status.required' => '状态不能为空',
                'resolution_option.required' => '分辨率不能为空',
            ]);
            $validate2['project_id'] = $project_id;
            $validate2['head_enable'] = $request->get('head_enable',0);
            if($validate2['head_enable']){
                if(!$request->head_video){
                    return back()->withInput()->withErrors([
                        'msg' => '视频片头里的视频不能为空',
                    ]);
                }
                if($request->id_head_video == 0){
                    $temp_ori_image = str_replace("/storage","",$projectRules->head_video);
                    // Storage::delete('public'.$temp_ori_image);
                    $temp_image = str_replace("/storage","",$request->head_video);
                    $image_name = str_replace("/storage/temp/","",$request->head_video);
                    $head_video = 'projectRule/'. $id . "/" . $image_name;
                    Storage::move('public'.$temp_image, 'public/'. $head_video);
                    $validate2['head_video'] = '/storage/' . $head_video;
                }
                $validate2['head_enable'] = 1;
            }else{
                $validate2['head_video'] = '';
            }

            $validate2['logo_enable'] = $request->get('logo_enable',0);
            if( $validate2['logo_enable']){
                if(!$request->logo_image){
                    return back()->withInput()->withErrors([
                        'msg' => 'Logo里的图片不能为空',
                    ]);
                }
                if($request->id_logo_image == 0){
                    $response = Video::checkValid(asset($request->logo_image), 1, 1);
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
                        return back()->withInput()->withErrors([
                            'msg' => 'Logo图' . $error,
                        ]);
                    }
                    $temp_ori_image = str_replace("/storage","",$projectRules->logo_image);
                    // Storage::delete('public'.$temp_ori_image);
                    $temp_image = str_replace("/storage","",$request->logo_image);
                    $image_name = str_replace("/storage/temp/","",$request->logo_image);
                    $logo_image = 'projectRule/'. $id . "/" . $image_name;
                    Storage::move('public'.$temp_image, 'public/'. $logo_image);
                    $validate2['logo_image'] = '/storage/' . $logo_image;
                }
                $validate2['logo_enable'] = 1;
            }else{
                $validate2['logo_image'] = '';
            }
            $validate2['logo_position'] = $request->get('logo_position',0);
            $validate2['logo_padding'] = $request->get('logo_padding',0);
            $validate2['logo_scale'] = $request->get('logo_scale',0);

            $validate2['font_enable'] = $request->get('font_enable',0);
            if($validate2['font_enable']){
                $validate2['font_enable'] = 1;
            }
            $validate2['font_text'] = $request->get('font_text','');
            $validate2['font_color'] = $request->get('font_color','');
            $validate2['font_size'] = $request->get('font_size',0);
            $validate2['font_position'] = $request->get('font_position',0);
            $validate2['font_interval'] = $request->get('font_interval',0);
            $validate2['font_scroll'] = $request->get('font_scroll',0);
            $validate2['font_border'] = $request->get('font_border',0);
            $validate2['font_shadow'] = $request->get('font_shadow',0);
            $validate2['font_space'] = $request->get('font_space',0);

            $validate2['ad_enable'] = $request->get('ad_enable',0);
            if($validate2['ad_enable']){
                if(!$request->ad_image){
                    return back()->withInput()->withErrors([
                        'msg' => '文字广告里的图片不能为空',
                    ]);
                }
                if($request->id_ad_image == 0){
                    $response = Video::checkValid(asset($request->ad_image), 1, 1);
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
                        return back()->withInput()->withErrors([
                            'msg' => '文字广告图' . $error,
                        ]);
                    }
                    $temp_ori_image = str_replace("/storage","",$projectRules->ad_image);
                    // Storage::delete('public'.$temp_ori_image);
                    $temp_image = str_replace("/storage","",$request->ad_image);
                    $image_name = str_replace("/storage/temp/","",$request->ad_image);
                    $ad_image = 'projectRule/'. $id . "/" . $image_name;
                    Storage::move('public'.$temp_image, 'public/'. $ad_image);
                    $validate2['ad_image'] = '/storage/' . $ad_image;
                }
                $validate2['ad_enable'] = 1;
            }else{
                $validate2['ad_image'] = '';
            }
            $validate2['ad_start'] = $request->get('ad_start',0);
           
            $validate2['m3u8_enable'] = $request->get('m3u8_enable',0);
            if( $validate2['m3u8_enable']){
                $validate2['m3u8_enable'] = 1;
            }
            $validate2['m3u8_encrypt'] = $request->get('m3u8_encrypt',0);
            $validate2['m3u8_interval'] = $request->get('m3u8_interval',10);
            $validate2['m3u8_bitrate'] = $request->get('m3u8_bitrate',0);
            $validate2['m3u8_fps'] = $request->get('m3u8_fps',0);

            $validate2['preview_enable'] = $request->get('preview_enable',0);
            if($validate2['preview_enable']){
                $validate2['preview_enable'] = 1;
            }
            $validate2['preview_interval'] = $request->get('preview_interval',10);
            $validate2['preview_skip'] = $request->get('preview_skip',10);

            $validate2['webp_enable'] = $request->get('webp_enable',0);
            if($validate2['webp_enable']){
                $validate2['webp_enable'] = 1;
            }
            $validate2['webp_start'] = $request->get('webp_start',5);
            $validate2['webp_interval'] = $request->get('webp_interval',1);
            $validate2['webp_count'] = $request->get('webp_count',1);
            $validate2['webp_length'] = $request->get('webp_length',10);

            $validate2['skip_enable'] = $request->get('skip_enable',0);
            if($validate2['skip_enable']){
                $validate2['skip_enable'] = 1;
            }
            $validate2['skip_head'] = $request->get('skip_head',30);
            $validate2['skip_back'] = $request->get('skip_back',30);

            $validate2['generate_subtitle'] = $request->get('generate_subtitle',0);
            // if($validate2['generate_subtitle']){
            //     $validate2['generate_subtitle'] = 1;
            // }
            $subtitleLanguage = $request->get('subtitle_languages','');
            $validate2['subtitle_languages'] = $subtitleLanguage ? json_encode($subtitleLanguage ) : '';
            
            $projectRules->update($validate2);
            DB::commit();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '编辑成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        $projectRules = ProjectRules::find($id);
        if($projectRules->head_video){
            $temp_ori_image = str_replace("/storage","",$projectRules->head_video);
            Storage::delete('public'.$temp_ori_image);
        }
        if($projectRules->ad_image){
            $temp_ori_image = str_replace("/storage","",$projectRules->ad_image);
            Storage::delete('public'.$temp_ori_image);
        }
        if($projectRules->logo_image){
            $temp_ori_image = str_replace("/storage","",$projectRules->logo_image);
            Storage::delete('public'.$temp_ori_image);
        }
        $projectRules->delete();
        return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
        $tag = ProjectRules::findOrFail($id);
        $tag->status = $request->get('status');
        $tag->save();
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
