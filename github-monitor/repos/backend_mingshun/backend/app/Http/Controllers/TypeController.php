<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Type;
use App\Trait\Import;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TypeController extends Controller
{
    use Import;
    public function __construct()
    {
        $this->init(Type::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Type::search($request)->select(sprintf('%s.*', (new Type())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $edit = 1;
                $delete = 1;
                if(Auth::user()->checkUserRole([3])){
                    $edit = 0;
                    $delete = 0;
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
                        'selection' => Type::STATUS,
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
            $columns =[
                "name" => ["name" => "名字"],
            ];
            $setting = [
                'create' => 0,
            ];
        }else{
            $columns =[
                "id" => ["name" => "ID"],
                "name" => ["name" => "名字"],
                "assigned_order" => ["name" => "分配顺序"],
                "status" => ["name" => "状态"]
            ];
            $setting = [
                'create' => 1,
                'import' => view('widget.import', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'import' => implode(",", Type::IMPORT)
                ]),
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' =>  [
                            'id' =>
                            [
                                'name' => 'id',
                                'type' => 'text',
                            ],
                            'status' => [
                                'name' => '状态',
                                'type' => Type::STATUS,
                            ],
                        ],
                    ]
                ),
            ];
        }


        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => $columns,
            'setting' => $setting,
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $content = view('form', [
                'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'name' => [
                    'name' => '名字',
                    'type' => 'text',
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  Type::STATUS,
                    'required' => 1,
                    'value' => 1
                ],
                'assigned_order' => [
                    'name' => '分配顺序',
                    'type' => 'number',
                    'required' => 1,
                    'value' => 0
                ],
            ],
             
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
                'name' => ['required', Rule::unique('types', 'name')],
                'status' => ['required'],
                'assigned_order' => ['required']
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
                'assigned_order.required' => '分配顺序不能为空',
            ]);
            $validate['name'] = trim($validate['name']);
            Type::create($validate);
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
        $type = Type::findOrFail($id);
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'name' => [
                    'name' => '名字',
                    'type' => 'text',
                    'value' => $type->name,
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  Type::STATUS,
                    'value' => $type->status,
                    'required' => 1
                ],
                'assigned_order' => [
                    'name' => '分配顺序',
                    'type' => 'number',
                    'required' => 1,
                    'value' => $type->assigned_order,
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
        try {
            $validate = $request->validate([
                'name' => ['required', Rule::unique('tags', 'name')->ignore($id)],
                'status' => ['required'],
                'assigned_order' => ['required']
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
                'assigned_order.required' => '分配顺序不能为空',
            ]);
            $validate['name'] = trim($validate['name']);
            $type = Type::findOrFail($id);
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
            $type = Type::find($id);
            $type->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $tag = Type::findOrFail($id);
            $tag->status = $request->get('status');
            $tag->save();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
