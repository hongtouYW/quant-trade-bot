<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function __construct()
    {
        $this->init(Server::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Server::search($request)->select(sprintf('%s.*', (new Server())->getTable()));
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

            $table->editColumn('created_by', function ($row) {
                if ($row->created_by == '0') {
                    return "接口传入";
                }
                return $row->createdBy->username;
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return view('widget.statusForm', [
                        'crudRoutePart' => $this->crudRoutePart,
                        'selection' => Server::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->editColumn('details', function ($row) {
                return '<b>名字 : </b>' . strip_tags($row->name) . '<br>' .
                    '<b>IP : </b>' . strip_tags($row->ip) . '<br>' .
                    '<b>域名 : </b>' . strip_tags($row->domain) . '<br>' .
                    '<b>播放域名 : </b>' . strip_tags($row->play_domain) . '<br>' .
                    '<b>lan域名 : </b>' . strip_tags($row->lan_domain) . '<br>' .
                    '<b>Idc : </b>' . strip_tags($row->idc) . '<br>' .
                    '<b>地址前缀 : </b>' . strip_tags($row->path);
                return '';
            });

            $table->editColumn('post_recommended', function ($row) {
                if ($row->post_recommended) {
                    return "是";
                }
                return "否";
            });
           
            $table->rawColumns(['actions','details']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" =>"id"],
                "details" => ["name" => "详情", "sort" => 0],
                "name" => ["name" =>"名字", "visible" => 0],
                "domain" => ["name" =>'域名', "visible" => 0],
                "play_domain" => ["name" =>'播放域名', "visible" => 0],
                "lan_domain" => ["name" =>'lan域名', "visible" => 0],
                "ip" => ["name" =>'ip', "visible" => 0],
                "path" => ["name" =>"地址前缀", "visible" => 0],
                "status" => ["name" =>"状态"],
                'post_recommended' => ["name" =>"存放帖子"],
                "created_by" => ["name" =>"创建者"],
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
                            'status' => [
                                'name' => '状态',
                                'type' => Server::STATUS,
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
                'domain' => [
                    'name' => '域名',
                    'type' => 'text',
                    'required' => 1
                ],
                'play_domain' => [
                    'name' => '播放域名',
                    'type' => 'text',
                    'required' => 1
                ],
                'lan_domain' => [
                    'name' => 'lan域名',
                    'type' => 'text',
                ],
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'required' => 1
                ],
                'idc' => [
                    'name' => 'idc',
                    'type' => 'text'
                ],
                'path' => [
                    'name' => '地址前缀',
                    'type' => 'text',
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  Server::STATUS,
                    'required' => 1,
                    'value' => 1
                ],
                'post_recommended' => [
                    'name' => '存放帖子',
                    'type' =>  [0=>'否',1=>'是'],
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
            'name' => ['required'],
            'domain' => ['required'],
            'play_domain' => ['required'],
            'lan_domain' => [],
            'ip' => ['required', Rule::unique('servers', 'ip')],
            'path' => ['required'],
            'status' => ['required'],
            'post_recommended' => ['required'],
            'idc' => []
        ], [
            'name.required' => '名字不能为空',
            'domain.required' => '域名不能为空',
            'play_domain.required' => '播放域名不能为空',
            'ip.required' => 'ip不能为空',
            'ip.unique' => 'ip已被使用',
            'path.required' => '地址前缀不能为空',
            'status.required' => '状态不能为空',
            'post_recommended.required' => '存放帖子不能为空',
        ]);
        $validate['created_by'] = Auth::user()->id;
        if($validate['post_recommended']){
            Server::where('post_recommended', 1)->update(['post_recommended' => 0]);
        }
        Server::create($validate);
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
        $server = Server::findOrFail($id);
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
                    'value' => $server->name,
                    'required' => 1
                ],
                'domain' => [
                    'name' => '域名',
                    'type' => 'text',
                    'value' => $server->domain,
                    'required' => 1
                ],
                'play_domain' => [
                    'name' => '播放域名',
                    'type' => 'text',
                    'value' => $server->play_domain,
                    'required' => 1
                ],
                'lan_domain' => [
                    'name' => 'lan域名',
                    'type' => 'text',
                    'value' => $server->lan_domain
                ],
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'value' => $server->ip,
                    'required' => 1
                ],
                'idc' => [
                    'name' => 'idc',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $server->idc
                ],
                'path' => [
                    'name' => '地址前缀',
                    'type' => 'text',
                    'value' => $server->path,
                    'required' => 1
                ],
                'status' => [
                    'name' => '状态',
                    'type' =>  Server::STATUS,
                    'value' => $server->status,
                    'required' => 1
                ],
                'post_recommended' => [
                    'name' => '存放帖子',
                    'type' =>  [0=>'否',1=>'是'],
                    'required' => 1,
                    'value' => $server->post_recommended
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
            'domain' => ['required'],
            'play_domain' => ['required'],
            'lan_domain' => [],
            'ip' => ['required', Rule::unique('servers', 'ip')->ignore($id)],
            'path' => ['required'],
            'status' => ['required'],
            'post_recommended' => ['required'],
            'idc' => []
        ], [
            'name.required' => '名字不能为空',
            'domain.required' => '域名不能为空',
            'play_domain.required' => '播放域名不能为空',
            'ip.required' => 'ip不能为空',
            'ip.unique' => 'ip已被使用',
            'path.required' => '地址前缀不能为空',
            'status.required' => '状态不能为空',
            'post_recommended.required' => '存放帖子不能为空',
        ]);
        $server = Server::findOrFail($id);
        if($validate['post_recommended']){
            Server::where('post_recommended', 1)->update(['post_recommended' => 0]);
        }
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
        $server = Server::find($id);
        if(!$server->ftps->isEmpty()){
            return back()->withInput()->withErrors([
                'msg' => $this->title . '删除失败。'.$this->title .'已被使用。',
            ]);
        }
        if(!$server->videos->isEmpty()){
            return back()->withInput()->withErrors([
                'msg' => $this->title . '删除失败。'.$this->title .'已被使用。',
            ]);
        }
        unset($server->ftps,$server->videos);
        $server->delete();
        return back()->with('success', $this->title.'删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        $tag = Server::findOrFail($id);
        $tag->status = $request->get('status');
        $tag->save();
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
    }
}
