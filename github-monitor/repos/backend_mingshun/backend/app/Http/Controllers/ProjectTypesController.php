<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectTypesController extends Controller
{
    public function __construct()
    {
        $this->init(ProjectTypes::class);
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
            $query = ProjectTypes::search($request)->select(sprintf('%s.*', (new ProjectTypes())->getTable()));
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
                        'selection' => ProjectTypes::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->editColumn('is_show', function ($row) {
                if ($row->is_show) {
                    return '是';
                }
                return "否";
            });

            $table->editColumn('project_id', function ($row) {
                if ($row->project_id) {
                    return $row->projects->name;
                }
                return '';
            });

            $table->rawColumns(['actions']);

            return $table->make(true);
        }
        $column = [
            "id" => ["name" => "ID"],
            "name" => ["name" => "名字"],
            "show_name" => ["name" => "显示名字"],
            "is_show" => ["name" => "是否显示"]
        ];
        if ($user->checkUserRole([1, 2])){
            $column['show_name'] =  ["name" => "显示名字"];
            $column['is_show'] = ["name" => "是否显示"];
        }
        $filters = [];
        $create = 0;
        if(!Auth::user()->checkUserRole([3])){
            $create = 1;
            $column['status'] = [
                'name' => '状态',
            ];
            if ($user->isSuperAdmin()) {
                $column['project_id'] = [
                    'name' => '项目',
                ];
                $filters['project_id'] = [
                    'name' => '项目',
                    'type' => 'select',
                    'route' => route('projects.select'),
                ];
            }
        }

        if (!$user->isSuperAdmin()) {
            $project_name = $projects->name;
        }else{
            $project_name = '全';
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
                        'filters' => $filters,
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
                'name' => '名字',
                'type' => 'text',
                'required' => 1
            ],
            'status' => [
                'name' => '状态',
                'type' =>  ProjectTypes::STATUS,
                'required' => 1,
                'value' => 1
            ]
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
        if ($user->checkUserRole([1, 2])){
            $column['show_name'] = [
                'name' => '显示名字',
                'type' => 'text',
            ];
            $column['is_show'] = [
                'name' => '是否显示',
                'type' =>  ProjectTypes::STATUS,
                'required' => 1,
                'value' => 0
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
            $user = Auth::user();
            $projects = $user->projects->first();
            if ($user->isSuperAdmin()) {
                $project_id = $request->get('project_id', '');
            }else{
                $project_id = $projects->id;
            }
            $validate = $request->validate([
                'name' => ['required', Rule::unique('project_types', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })],
                'status' => ['required'],
                'project_id' => [],
                'show_name' => [],
                'is_show' => [],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
            ]);
            $validate['project_id'] = $project_id;
            ProjectTypes::create($validate);
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '添加成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $type = ProjectTypes::findOrFail($id);
        $column = [
            'name' => [
                'name' => '名字',
                'type' => 'text',
                'value' => $type->name,
                'required' => 1
            ],
            'status' => [
                'name' => '状态',
                'type' =>  ProjectTypes::STATUS,
                'required' => 1,
                'value' => $type->status,
            ]
        ];
        $user = Auth::user();
        $projects = $user->projects->first();
        if ($user->isSuperAdmin()) {
            $column['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'required' => 1,
                'route' => route('projects.select'),
                'value' => $type->project_id,
                'label' => $type->projects?->name,
            ];
        }
        if ($user->checkUserRole([1, 2])){
            $column['show_name'] = [
                'name' => '显示名字',
                'type' => 'text',
                'value' => $type->show_name
            ];
            $column['is_show'] = [
                'name' => '是否显示',
                'type' =>  ProjectTypes::STATUS,
                'required' => 1,
                'value' => $type->is_show
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
            $user = Auth::user();
            $projects = $user->projects->first();
            if ($user->isSuperAdmin()) {
                $project_id = $request->get('project_id', '');
            }else{
                $project_id = $projects->id;
            }
            $validate = $request->validate([
                'name' => ['required', 'name' => ['required', Rule::unique('project_types', 'name')->where(function ($query) use($project_id) {
                    return $query->where('project_id', $project_id);
                })->ignore($id)]],
                'status' => ['required'],
                'project_id' => [],
                'show_name' => [],
                'is_show' => [],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
            ]);
            $validate['project_id'] = $project_id;
            $type = ProjectTypes::findOrFail($id);
            $type->update($validate);
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
            $type = ProjectTypes::find($id);
            $type->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $tag = ProjectTypes::findOrFail($id);
            $tag->status = $request->get('status');
            $tag->save();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function select(Request $request)
    {
        $projectIdArray = Auth::user()->projects->pluck('id')->toArray();
        if($projectIdArray){
            
            return $this->baseSelect($request, $this->model::whereIn('project_id',$projectIdArray));
        }
        return $this->baseSelect($request, $this->model::query());
    }

    public function showSelect(Request $request)
    {
        $search = $request->get('q', '');
        $query = ProjectTypes::query()
            ->select('id', 'show_name as text')
            ->where('is_show',1);

        if (!empty($search)) {
            $query->where(DB::raw('LOWER(show_name)'), 'LIKE', '%' . strtolower($search) . '%');
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
