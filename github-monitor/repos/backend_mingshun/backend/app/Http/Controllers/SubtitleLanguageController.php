<?php

namespace App\Http\Controllers;

use App\Models\SubtitleLanguage;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use DataTables;

class SubtitleLanguageController extends Controller
{
    public function __construct()
    {
        $this->init(SubtitleLanguage::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = SubtitleLanguage::search($request)->select(sprintf('%s.*', (new SubtitleLanguage())->getTable()));
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
                        'selection' => SubtitleLanguage::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->rawColumns(['actions']);

            return $table->make(true);
        }

        $columns =[
            "id" => ["name" => "ID"],
            "name" => ["name" => "名字"],
            "label" => ["name" => "code"],
            "status" => ["name" => "状态"]
        ];
        $setting = [
            'create' => 1,
            'filters' => view(
                'widget.dataTableFilter',
                [
                    'filters' =>  [
                        'status' => [
                            'name' => '状态',
                            'type' => SubtitleLanguage::STATUS,
                        ],
                    ],
                ]
            ),
        ];

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
                'label' => [
                    'name' => 'code',
                    'type' => 'text',
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  SubtitleLanguage::STATUS,
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
                'name' => ['required'],
                'status' => ['required'],
                'label' => ['required', Rule::unique('subtitle_languages', 'label')]
            ], [
                'name.required' => '名字不能为空',
                'label.unique' => 'code已被使用',
                'status.required' => '状态不能为空',
                'label.required' => 'code不能为空',
            ]);
            SubtitleLanguage::create($validate);
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
        $subtitleLanguage = SubtitleLanguage::findOrFail($id);
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
                    'value' => $subtitleLanguage->name,
                    'required' => 1
                ],
                'label' => [
                    'name' => 'code',
                    'type' => 'text',
                    'value' => $subtitleLanguage->label,
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  SubtitleLanguage::STATUS,
                    'value' => $subtitleLanguage->status,
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
                'name' => ['required'],
                'status' => ['required'],
                'label' => ['required', Rule::unique('subtitle_languages', 'label')->ignore($id)]
            ], [
                'name.required' => '名字不能为空',
                'label.unique' => 'code已被使用',
                'status.required' => '状态不能为空',
                'label.required' => 'code不能为空',
            ]);
            $subtitleLanguage = SubtitleLanguage::findOrFail($id);
            $subtitleLanguage->update($validate);
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
            $subtitleLanguage = SubtitleLanguage::find($id);
            $subtitleLanguage->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $subtitleLanguage = SubtitleLanguage::findOrFail($id);
            $subtitleLanguage->status = $request->get('status');
            $subtitleLanguage->save();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
