<?php

namespace App\Http\Controllers;

use App\Models\PhotoProjectRule;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PhotoProjectRuleController extends Controller
{
    public function __construct()
    {
        $this->init(PhotoProjectRule::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = PhotoProjectRule::search($request)->select(sprintf('%s.*', (new PhotoProjectRule())->getTable()));
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

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return view('widget.statusForm', [
                        'crudRoutePart' => $this->crudRoutePart,
                        'selection' => PhotoProjectRule::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->rawColumns(['actions']);

            return $table->make(true);
        }

        if(Auth::user()->checkUserRole([3])){
            $setting = [
                'create' => 1,
            ];
        }else if(Auth::user()->checkUserRole([6])){
            $setting = [
                'create' => 1,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' =>  [
                            'id' =>
                            [
                                'name' => 'id',
                                'type' => 'text',
                            ],
                        ],
                    ]
                ),
            ];
        }else{
            $setting = [
                'create' => 1,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' =>  [
                            'id' =>
                            [
                                'name' => 'id',
                                'type' => 'text',
                            ],
                            'project_id' =>
                            [
                                'name' => '项目',
                                'type' => 'select',
                                'route' => route('projects.select'),
                            ],
                        ],
                    ]
                ),
            ];
        }


        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "name" => ["name" => "名字"],
                "status" => ["name" => "状态"]
            ],
            'setting' => $setting,
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
                'name' => '名字',
                'type' => 'text',
                'required' => 1
            ],
            'status' => [
                'name' => '状态',
                'type' =>  PhotoProjectRule::STATUS,
                'required' => 1,
                'value' => 1
            ],
            'font_enable' => [
                'name' => '添加字体',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'font_position' => [
                        'name' => '位置',
                        'type' =>  PhotoProjectRule::LOGO,
                        'required' => 1,
                    ],
                    'font_borderSpace' => [
                        'name' => '距离视频边框(px)',
                        'type' =>  'number',
                        'required' => 1,
                    ],
                    'font_fontName' => [
                        'name' => '字体名称',
                        'type' =>  PhotoProjectRule::PICTUREFONTNAME,
                        'required' => 1,
                    ],
                    'font_fontSize' => [
                        'name' => '字体大小(px)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => 20,
                        'placeholder' => 20
                    ],
                    'font_fontColor' => [
                        'name' => '字体颜色',
                        'type' =>  'color',
                        'required' => 1,
                        'value' => '#000000',
                        'placeholder' => '#000000'
                    ],
                    'font_text1' => [
                        'name' => '文字1',
                        'type' =>  'text',
                        'required' => 1,
                    ],
                    'font_text2' => [
                        'name' => '文字2',
                        'type' =>  'text',
                    ],
                    'font_text3' => [
                        'name' => '文字3',
                        'type' =>  'text',
                    ],
                ]
            ],
            'icon_enable' => [
                'name' => '添加logo',
                'type' =>  'switch',
                'required' => 1,
                'value' => 0,
                'setting' => [
                    'icon_path' => [
                        'name' => 'Logo(40*40)',
                        'type' => 'file',
                        'required' => 1,
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ]
                    ],
                    'icon_position' => [
                        'name' => '位置',
                        'type' =>  PhotoProjectRule::LOGO,
                        'required' => 1,
                    ],
                    'icon_padding' => [
                        'name' => '边框(px)',
                        'type' => 'number',
                        'required' => 1,
                        'value' => 10,
                        'placeholder' => 10
                    ],
                ]
            ],
        ];

        $user = Auth::user();
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
            'columns' => $column
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
                'name' => ['required', Rule::unique('photo_project_rules', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })],
                'status' => ['required'],
                'font_enable' => ['required'],
                'font_position' => [],
                'font_borderSpace' => [],
                'font_fontName' => [],
                'font_fontSize' => [],
                'font_fontColor' => [],
                'font_lineSpace' => [],
                'font_text1' => [],
                'font_text2' => [],
                'font_text3' => [],
                'icon_enable' => ['required'],
                'icon_path' => [],
                'icon_position' => [],
                'icon_padding' => [],
                'icon_scale' => [],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
                'font_enable.required' => '添加字体不能为空',
                'icon_enable.required' => '添加图片不能为空',
            ]);
            if($validate['font_enable']){
                $validate['font_lineSpace'] = 1;
            }
            if($validate['icon_enable']){
                $validate['icon_scale'] = 100;
            }
            $validate['project_id'] = $project_id;
            $validate2['icon_enable'] = $validate['icon_enable'];
            $validate2['icon_position'] = $validate['icon_position'];
            $temp = $validate['icon_path'];
            unset($validate['icon_enable'],$validate['icon_position'],$validate['icon_path']);
            $photoProjectRules = PhotoProjectRule::create($validate);
            $id = $photoProjectRules->id;
            if($validate2['icon_enable']){
                if(!$temp){
                    return back()->withInput()->withErrors([
                        'msg' => '添加图片里的图片不能为空',
                    ]);
                }
                $temp_image = str_replace("/storage","",$temp);
                $image_name = str_replace("/storage/temp/","",$temp);
                $icon_path = 'projectRule/'. $id . "/" . $image_name;
                Storage::move('public'.$temp_image, 'public/'. $icon_path);
                $validate2['icon_path'] = '/storage/' . $icon_path;
                $validate2['icon_enable'] = 1;
            }else{
                $validate2['icon_path'] = '';
            }
            $photoProjectRules->update($validate2);
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
        $photoProjectRule = PhotoProjectRule::findOrFail($id);
        $column = [
            'name' => [
                'name' => '名字',
                'type' => 'text',
                'required' => 1,
                'value' => $photoProjectRule->name
            ],
            'status' => [
                'name' => '状态',
                'type' =>  PhotoProjectRule::STATUS,
                'required' => 1,
                'value' => $photoProjectRule->status
            ],
            'font_enable' => [
                'name' => '添加字体',
                'type' =>  'switch',
                'required' => 1,
                'value' => $photoProjectRule->font_enable,
                'setting' => [
                    'font_position' => [
                        'name' => '位置',
                        'type' =>  PhotoProjectRule::LOGO,
                        'required' => 1,
                        'value' => $photoProjectRule->font_position
                    ],
                    'font_borderSpace' => [
                        'name' => '距离视频边框(px)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $photoProjectRule->font_borderSpace
                    ],
                    'font_fontName' => [
                        'name' => '字体名称',
                        'type' =>  PhotoProjectRule::PICTUREFONTNAME,
                        'required' => 1,
                        'value' => $photoProjectRule->font_fontName
                    ],
                    'font_fontSize' => [
                        'name' => '字体大小(px)',
                        'type' =>  'number',
                        'required' => 1,
                        'value' => $photoProjectRule->font_fontSize,
                        'placeholder' => 20
                    ],
                    'font_fontColor' => [
                        'name' => '字体颜色',
                        'type' =>  'color',
                        'required' => 1,
                        'value' => $photoProjectRule->font_fontColor,
                        'placeholder' => '#000000'
                    ],
                    'font_text1' => [
                        'name' => '文字1',
                        'type' =>  'text',
                        'required' => 1,
                        'value' => $photoProjectRule->font_text1
                    ],
                    'font_text2' => [
                        'name' => '文字2',
                        'type' =>  'text',
                        'value' => $photoProjectRule->font_text2
                    ],
                    'font_text3' => [
                        'name' => '文字3',
                        'type' =>  'text',
                        'value' => $photoProjectRule->font_text3
                    ],
                ]
            ],
            'icon_enable' => [
                'name' => '添加logo',
                'type' =>  'switch',
                'required' => 1,
                'value' => $photoProjectRule->icon_enable,
                'setting' => [
                    'icon_path' => [
                        'name' => 'Logo(40*40)',
                        'type' => 'file',
                        'required' => 1,
                        'value' => [
                            'id' => $photoProjectRule->id,
                            'src' => $photoProjectRule->icon_path,
                        ],
                        'setting' => [
                            'type' => 'image',
                            'tempUploadUrl' => route('tempUpload')
                        ]
                    ],
                    'icon_position' => [
                        'name' => '位置',
                        'type' =>  PhotoProjectRule::LOGO,
                        'required' => 1,
                        'value' => $photoProjectRule->icon_position
                    ],
                    'icon_padding' => [
                        'name' => '边框(px)',
                        'type' => 'number',
                        'required' => 1,
                        'value' => $photoProjectRule->icon_padding,
                        'placeholder' => 10
                    ],
                ]
            ],
        ];
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            $column['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'required' => 1,
                'route' => route('projects.select'),
                'value' => $photoProjectRule->project_id,
                'label' => $photoProjectRule->projects?->name,
            ];
        }
        $content = view('form', [
            'extra' => '',
            'edit' => 1,
            'id' => $id,
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
            $user = Auth::user();
            $photoProjectRules = PhotoProjectRule::findOrFail($id);
            $projects = $user->projects->first();
            if ($user->isSuperAdmin()) {
                $project_id = $request->get('project_id', '');
            }else{
                $project_id = $projects->id;
            }
            $validate = $request->validate([
                'name' => ['required', Rule::unique('project_rules', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })->ignore($id)],
                'status' => ['required'],
                'font_enable' => ['required'],
                'font_position' => [],
                'font_borderSpace' => [],
                'font_fontName' => [],
                'font_fontSize' => [],
                'font_fontColor' => [],
                'font_lineSpace' => [],
                'font_text1' => [],
                'font_text2' => [],
                'font_text3' => [],
                'icon_enable' => ['required'],
                'icon_path' => [],
                'icon_position' => [],
                'icon_padding' => [],
                'icon_scale' => [],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
                'font_enable.required' => '添加字体不能为空',
                'icon_enable.required' => '添加图片不能为空',
            ]);
            $validate['project_id'] = $project_id;
            $temp = $validate['icon_path'];
            if($validate['font_enable']){
                $validate['font_lineSpace'] = 1;
            }else{
                $validate['font_lineSpace'] = 0;
            }
            if($validate['icon_enable']){
                $validate['icon_scale'] = 100;
            }else{
                $validate['icon_scale'] = 0;
            }
            if($validate['icon_enable']){
                if(!$validate['icon_path']){
                    return back()->withInput()->withErrors([
                        'msg' => '视频片头里的视频不能为空',
                    ]);
                }
                if($request->id_icon_path == 0){
                    $temp_image = str_replace("/storage","",$validate['icon_path']);
                    $image_name = str_replace("/storage/temp/","",$validate['icon_path']);
                    $icon_path = 'projectRule/'. $id . "/" . $image_name;
                    Storage::move('public'.$temp_image, 'public/'. $icon_path);
                    $validate['icon_path'] = '/storage/' . $icon_path;
                }
                $validate['icon_enable'] = 1;
            }else{
                $validate['icon_path'] = '';
            }
            $photoProjectRules->update($validate);
            DB::commit();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '添加成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        $photoProjectRule = PhotoProjectRule::find($id);
        $photoProjectRule->delete();
        return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
        $photoProjectRule = PhotoProjectRule::findOrFail($id);
        $photoProjectRule->status = $request->get('status');
        $photoProjectRule->save();
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
