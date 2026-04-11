<?php

namespace App\Http\Controllers;

use App\Models\CutServer;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CutServerController extends Controller
{
    public function __construct()
    {
        $this->init(CutServer::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = CutServer::search($request)->select(sprintf('%s.*', (new CutServer())->getTable()));
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
           
            $table->rawColumns(['actions']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" =>"id"],
                "ip" => ["name" =>'ip'],
                "idc" => ["name" =>'idc'],
                "redis_port" => ["name" =>'redis端口'],
                "redis_db" => ["name" =>'redisDB'],
                "redis_password" => ["name" =>'redis密码'],
                "created_at" => ["name" =>"创建时间"],
                "updated_at" => ["name" =>"更新时间"]
            ],
            'setting' => [
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
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'required' => 1
                ],
                'idc' => [
                    'name' => 'idc',
                    'type' => 'text'
                ],
                'redis_port' => [
                    'name' => 'redis端口',
                    'type' => 'text',
                    'required' => 1
                ],
                'redis_db' => [
                    'name' => 'redisDB',
                    'type' => 'text',
                    'required' => 1,
                ],
                'redis_password' => [
                    'name' => 'redis密码',
                    'type' => 'text',
                    'required' => 1,
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
                'ip' => ['required'],
                'redis_port' => ['required'],
                'redis_db' => ['required'],
                'redis_password' => ['required'],
                'idc' => []
            ], [
                'redis_port.required' => 'redis端口不能为空',
                'redis_db.required' => 'redisDB不能为空',
                'ip.required' => 'ip不能为空',
                'redis_password.required' => 'redis密码不能为空',
            ]);
            CutServer::create($validate);
            return redirect()->route($this->crudRoutePart.'.index')->with('success', $this->title.'添加成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $server = CutServer::findOrFail($id);
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->ip
                ],
                'idc' => [
                    'name' => 'idc',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->idc
                ],
                'redis_port' => [
                    'name' => 'redis端口',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->redis_port
                ],
                'redis_db' => [
                    'name' => 'redisDB',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->redis_db,
                ],
                'redis_password' => [
                    'name' => 'redis密码',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->redis_password,
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
                'ip' => ['required'],
                'redis_port' => ['required'],
                'redis_db' => ['required'],
                'redis_password' => ['required'],
                'idc' => []
            ], [
                'redis_port.required' => 'redis端口不能为空',
                'redis_db.required' => 'redisDB不能为空',
                'ip.required' => 'ip不能为空',
                'redis_password.required' => 'redis密码不能为空',
            ]);
            $server = CutServer::findOrFail($id);
            $server->update($validate);
            return redirect()->route($this->crudRoutePart.'.index')->with('success', $this->title.'编辑成功');
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
            $cutServer = CutServer::find($id);
            $cutServer->delete();
            return back()->with('success', $this->title.'删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
