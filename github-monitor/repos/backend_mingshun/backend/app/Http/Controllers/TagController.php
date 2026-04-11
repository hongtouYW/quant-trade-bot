<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Tag;
use App\Trait\Export;
use App\Trait\Import;
use Illuminate\Http\Request;
use DataTables;
use Exception;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    use Import;
    use Export;
    public function __construct()
    {
        $this->init(Tag::class);
        $this->header = [
            '#' => '#',
            'id' => 'ID',
            'name' => '名字'
        ];
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Tag::search($request)->select(sprintf('%s.*', (new Tag())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => 1,
                    'delete' => 1,
                    'isButton' => 1
                ]);
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return view('widget.statusForm', [
                        'crudRoutePart' => $this->crudRoutePart,
                        'selection' => Tag::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->rawColumns(['actions', 'status']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "name" => ["name" => "名字"],
                "status" => ["name" => "状态"]
            ],
            'setting' => [
                'create' => 1,
                'export' => 1,
                'import' => view('widget.import', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'import' => implode(",", Tag::IMPORT)
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
                                'type' => Tag::STATUS,
                            ],
                        ],
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
                    'type' =>  Tag::STATUS,
                    'required' => 1,
                    'value' => 1
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
                'name' => ['required', Rule::unique('tags', 'name')],
                'status' => ['required'],
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
            ]);
            $validate['name'] = trim($validate['name']);
            Tag::create($validate);
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
        $tag = Tag::findOrFail($id);
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
                    'value' => $tag->name,
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  Tag::STATUS,
                    'value' => $tag->status,
                    'required' => 1
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
            ], [
                'name.required' => '名字不能为空',
                'name.unique' => '名字已被使用',
                'status.required' => '状态不能为空',
            ]);
            $validate['name'] = trim($validate['name']);
            $tag = Tag::findOrFail($id);
            $tag->update($validate);
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
            $tag = Tag::find($id);
            $tag->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $tag = Tag::findOrFail($id);
            $tag->status = $request->get('status');
            $tag->save();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
