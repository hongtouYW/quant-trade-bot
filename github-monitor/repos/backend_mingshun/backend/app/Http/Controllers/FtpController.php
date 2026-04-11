<?php

namespace App\Http\Controllers;

use App\Models\Ftp;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FtpController extends Controller
{
    public function __construct()
    {
        $this->init(FTP::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = [];
        $create = 0;
        $delete = 0;
        $ftpReset = 0;
        if (Auth::user()->isSuperAdmin()) {
            $filters =
                [
                    'user_id' => [
                        'name' => '用户',
                        'type' => 'select',
                        'route' => route('users.select'),
                    ],
                    'server_id' => [
                        'name' => '服务器',
                        'type' => 'select',
                        'route' => route('servers.select'),
                    ]
                ];
            $create = 1;
            $delete = 1;
            $ftpReset = 1;
        }

        if ($request->ajax()) {
            $query = Ftp::search($request)->select(sprintf('%s.*', (new Ftp())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('user_id', function ($row) {
                return $row->user->username;
            });

            $table->editColumn('actions', function ($row) use ($delete) {
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => 1,
                    'delete' => $delete,
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
                "id" => ["name" => "ID"],
                'nickname' => ["name" =>'名字'],
                "user_id" => ["name" =>"用户名"],
                "path" => ["name" =>"FTP路径"],
                'created_at' => ["name" =>'创建时间'],
                'updated_at' => ["name" =>'更新时间']
            ],
            'setting' => [
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => $filters
                    ]
                ),
                'create' => $create,
                'ftpReset' => $ftpReset
            ],
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Auth::user()->id == 3) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '用户无权限添加新的ftp',
            ]);
        }
        $content = view('form', [
            'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'user_id' => [
                    'name' => '用户名',
                    'type' => 'select',
                    'required' => 1,
                    'route' => route('users.select')
                ],
                'password' => [
                    'name' => '密码',
                    'type' =>  'password',
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
            if (Auth::user()->id == 3) {
                return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                    'msg' => '用户无权限添加新的ftp',
                ]);
            }
            $validate = $request->validate([
                'user_id' => ['required'],
                'password' => ['required'],
            ], [
                'user_id.required' => '用户名不能为空',
                'password.required' => '密码不能为空',
            ]);
            $ftp = Ftp::where('user_id', $validate['user_id'])->first();
            if ($ftp) {
                return back()->withInput()->withErrors([
                    'msg' => '此用户已在此服务器有Ftp账号',
                ]);
            }
            $user = User::findOrFail($validate['user_id']);
            $validate['path'] = Ftp::createFtp($user->username, $validate['password']);
            $validate['nickname'] = $user->username;
            if (!$validate['path']) {
                return back()->withInput()->withErrors([
                    'msg' => '在服务器创建ftp失败',
                ]);
            }
            Ftp::create($validate);
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
        $ftp = Ftp::findOrFail($id);
        if (Auth::user()->id == 3 && $ftp->user_id != Auth::user()->id) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '用户无权限修改别人的ftp',
            ]);
        }
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'nickname' => [
                    'name' => '名字',
                    'type' => 'text',
                    'value' => $ftp->nickname,
                    'required' => 1,
                    'readonly' => 1
                ],
                'user_id' => [
                    'name' => '用户名',
                    'type' => 'select',
                    'required' => 1,
                    'readonly' => 1,
                    'route' => route('users.select'),
                    'value' => $ftp->user_id,
                    'label' => $ftp->user?->username,
                ],
                'server_id' => [
                    'name' => '服务器',
                    'type' => 'select',
                    'readonly' => 1,
                    'required' => 1,
                    'route' => route('servers.select'),
                    'value' => $ftp->server_id,
                    'label' => $ftp->servers?->name . ' (' . $ftp->servers?->ip . ')',
                ],
                'password' => [
                    'name' => '密码',
                    'type' =>  'password',
                    'required' => 1,
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
                'password' => ['required'],
            ], [
                'password.required' => '密码不能为空',
            ]);
            $ftp = Ftp::findOrFail($id);
            if (Auth::user()->id == 3 && $ftp->user_id != Auth::user()->id) {
                return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                    'msg' => '用户无权限修改别人的ftp',
                ]);
            }
            $isEdit = Ftp::editFtp($ftp->nickname, $validate['password']);
            if (!$isEdit) {
                return back()->withInput()->withErrors([
                    'msg' => '在服务器编辑ftp失败',
                ]);
            }
            $ftp = Ftp::findOrFail($id);
            $ftp->update($validate);
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
            $ftp = Ftp::findOrFail($id);
            $isDelete = Ftp::deleteFtp($ftp->nickname);
            if (!$isDelete) {
                return back()->withInput()->withErrors([
                    'msg' => '在服务器删除ftp失败',
                ]);
            }
            $ftp->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function ftpReset(){
        try {
            $ftps = Ftp::all();
            foreach($ftps as $ftp){
                $user = User::findOrFail($ftp->user_id);
                $path = Ftp::createFtpNoError($user->username, $ftp->password,$ftp->path);
                if($path){
                    if($path != $ftp->path){
                        $ftp->path = $path;
                        $ftp->save();
                    }
                }
            }
            return back()->with('success', $this->title . '同步成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
