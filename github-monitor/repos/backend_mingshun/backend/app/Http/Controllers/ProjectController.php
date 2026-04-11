<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Project;
use App\Models\ProjectServers;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->init(Project::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Project::search($request)->select(sprintf('%s.*', (new Project())->getTable()));
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

            $table->editColumn('server', function ($row) {
                $server = $row->servers->first();
                $text = "";
                if($server){
                    $text .= '<b>IP : </b>' .strip_tags(($server->ip ?? '')) . '<br>' .
                    '<b>Port : </b>' . strip_tags(($server->port ?? '')) . '<br>' .
                    '<b>路径 : </b>' . strip_tags(($server->path ?? '')) . '<br>' .
                    '<b>绝对路径 : </b>' . strip_tags(($server->absolute_path ?? '')) . '<br>' .
                    '<b>服务器用户 : </b>' . strip_tags(($server->username ?? '')) . '<br>';
                }
                return $text;
            });

            $table->editColumn('daily_cut', function ($row) {
                return '<b>每日切片 : </b>' .strip_tags(($row->daily_cut ?? '')) . '<br>' .
                '<b>每日切片限制 : </b>' . strip_tags(($row->daily_cut_quota ?? ''));
            });

            $table->editColumn('setting', function ($row) {
                return '<b>4K : </b>' .  strip_tags(Project::YESNO[$row->enable_4k]) . '<br>' .
                '<b>图片 : </b>' .  strip_tags(Project::YESNO[$row->enable_photo]) . '<br>' .
                '<b>直切 : </b>' . strip_tags(Project::YESNO[$row->direct_cut ]). '<br>' .
                '<b>直切隐藏 : </b>' . strip_tags(Project::YESNO[$row->solo ]). '<br>' .
                '<b>Redis DB : </b>' . strip_tags($row->redis_db);
            });

            $table->rawColumns(['actions', 'status', 'server', 'daily_cut','setting']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" =>"ID"],
                "name" => ["name" =>"名字"],
                'server' =>  ["name" =>"服务器", 'sort' => 0],
                "daily_cut"=> ["name" =>"每日切片"],
                "setting"=> ["name" =>"配置", 'sort' => 0],
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
                'name' => [
                    'name' => '名字',
                    'type' => 'text',
                    'required' => 1
                ],
                'enable_4k' => [
                    'name' => '4K',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => 1
                ],
                'enable_photo' => [
                    'name' => '图片',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => 0
                ],
                'direct_cut' => [
                    'name' => '直切',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => 1
                ],
                'solo' => [
                    'name' => '直切隐藏',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => 0,
                    'condition' => [
                        'direct_cut' => 1,
                    ]
                ],
                'daily_cut_quota' => [
                    'name' => '每日切片限制',
                    'type' => 'number',
                    'required' => 1,
                    'value' => Config::getCachedConfig('default_project_daily_cut_quota') ?? 20
                ],
                'redis_db' => [
                    'name' => 'Redis DB',
                    'type' => 'number',
                    'required' => 1,
                    'value' => Config::getCachedConfig('redis_db_default') ?? 3,
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
                'enable_4k' => ['required'],
                'enable_photo' => ['required'],
                'solo' => ['required'],
                'direct_cut' => ['required'],
                'daily_cut_quota' => ['required'],
                'redis_db' => ['required'],
            ], [
                'name.required' => '名字不能为空',
                'enable_4k.required' => '4K不能为空',
                'enable_photo.required' => '图片不能为空',
                'solo.required' => '直切隐藏不能为空',
                'direct_cut.required' => '直切不能为空',
                'daily_cut_quota.required' => '每日切片限制不能为空',
                'redis_db.required' => 'Redis DB不能为空',
            ]);
            $randomString = bin2hex(random_bytes(4));
            $processId = getmypid();
            $validate['token'] = uniqid($randomString . $processId);
            $validate['daily_cut'] = 0;
            Project::create($validate);
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
        $project = Project::findOrFail($id);
        $server = $project->servers->first();
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
                    'value' => $project->name,
                    'required' => 1
                ],
                'enable_4k' => [
                    'name' => '4K',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => $project->enable_4k
                ],
                'enable_photo' => [
                    'name' => '图片',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => $project->enable_photo
                ],
                'direct_cut' => [
                    'name' => '直切',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => $project->direct_cut
                ],
                'solo' => [
                    'name' => '直切隐藏',
                    'type' => Project::YESNO,
                    'required' => 1,
                    'value' => $project->solo,
                    'condition' => [
                        'direct_cut' => 1,
                    ]
                ],
                'daily_cut' => [
                    'name' => '每日切片',
                    'type' => 'number',
                    'required' => 1,
                    'value' => $project->daily_cut
                ],
                'daily_cut_quota' => [
                    'name' => '每日切片限制',
                    'type' => 'number',
                    'required' => 1,
                    'value' => $project->daily_cut_quota
                ],
                'redis_db' => [
                    'name' => 'Redis DB',
                    'type' => 'number',
                    'required' => 1,
                    'value' => $project->redis_db,
                ],
                'reminder' => [
                    'name' => '提醒',
                    'type' => 'html',
                    'value' => '请确保项目服务器已经对明顺服务器进行免密，并且填写的目录已经创建'
                ],
                'ip' => [
                    'name' => 'ip',
                    'type' => 'text',
                    'value' => $server->ip ?? '',
                ],
                'port' => [
                    'name' => 'port',
                    'type' => 'number',
                    'value' => $server->port ?? ''
                ],
                'reminder2' => [
                    'name' => '提醒',
                    'type' => 'html',
                    'value' => '<span style="color:red;">请确保路径和绝对路径不要带有敏感字，如“av”，“成人“，”18“ 等</span>'
                ],
                'path' => [
                    'name' => '路径',
                    'type' => 'text',
                    'value' => $server->path ?? '',
                ],
                'absolute_path' => [
                    'name' => '绝对路径',
                    'type' => 'text',
                    'value' => $server->absolute_path ?? '',
                ],
                'username' => [
                    'name' => '服务器用户',
                    'type' => 'text',
                    'value' => $server->username ?? '',
                ],
                'token' => [
                    'name' => '接口token',
                    'type' => 'text',
                    'value' => $project->token ?? '',
                    'readonly' => 1
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
                'enable_4k' => ['required'],
                'enable_photo' => ['required'],
                'solo' => ['required'],
                'direct_cut' => ['required'],
                'daily_cut' => ['required'],
                'daily_cut_quota' => ['required'],
                'redis_db' => ['required'],
                'ip' => [],
                'port' => [],
                'path' => [],
                'absolute_path' => [],
                'username' => [],
            ], [
                'name.required' => '名字不能为空',
                'enable_4k.required' => '4K不能为空',
                'enable_photo.required' => '图片不能为空',
                'solo.required' => '直切隐藏不能为空',
                'direct_cut.required' => '直切不能为空',
                'daily_cut.required' => '每日切片不能为空',
                'daily_cut_quota.required' => '每日切片限制不能为空',
                'redis_db.required' => 'Redis DB不能为空',
            ]);
            $project = Project::findOrFail($id);
            $project->update($validate);
            unset($validate['name'],$validate['solo'],$validate['enable_4k'],$validate['enable_photo'],$validate['direct_cut'],$validate['english_name']
                ,$validate['daily_cut'],$validate['daily_cut_quota'],$validate['redis_db']);
            if($validate['ip']??'' && $validate['port']??''
                && $validate['path']??'' && $validate['absolute_path']??''
                && $validate['username']??''){
                    ProjectServers::updateOrInsert(
                        ['project_id'=>$project->id],
                        $validate
                    );
            }
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
            $project = Project::find($id);
            if(!$project->users->isEmpty()){
                return back()->withInput()->withErrors([
                    'msg' => $this->title . '删除失败。'.$this->title .'已被使用。',
                ]);
            }
            unset($project->users);
            $project->delete();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
