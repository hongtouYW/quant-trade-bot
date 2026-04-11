<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Ftp;
use App\Models\User;
use Illuminate\Http\Request;
use DataTables;

class ConfigController extends Controller
{
    public function __construct()
    {
        $this->init(Config::class);
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Config::select(sprintf('%s.*', (new Config())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => 1,
                    'delete' => 0,
                    'isButton' => 1
                ]);
            });

            $table->rawColumns(['actions', 'status']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "description" => ["name" => "描述"],
                "value" => ["name" => "值"]
            ],
            'setting' => [
                "create" => 0
            ]
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $config = Config::findOrFail($id);
        $content = view('form', [
            'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'description' => [
                    'name' => '描述',
                    'type' => 'text',
                    'value' => $config->description,
                    'readonly' => 1
                ],
                'value' => [
                    'name' => '值',
                    'type' => 'text',
                    'value' => $config->value,
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
                'value' => ['required'],
            ], [
                'value.required' => '值不能为空',
            ]);
            $config = Config::findOrFail($id);
            $config->update($validate);
            Config::clearConfigCache($config->key);
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
