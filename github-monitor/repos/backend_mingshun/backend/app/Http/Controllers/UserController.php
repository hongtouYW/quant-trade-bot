<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Helper;
use App\Models\Ftp;
use App\Models\Project;
use App\Models\Server;
use App\Models\TokenLogs;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct()
    {
        $this->init(User::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = User::search($request)->select(sprintf('%s.*', (new User())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                if ($row->id == Auth::user()->id || $row->id == 1) {
                    $delete = 0;
                } else {
                    $delete = 1;
                }
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => 1,
                    'delete' => $delete,
                    'isButton' => 1
                ]) . view('widget.viewActionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'id' => $row->id,
                    'isButton' => 1
                ]);;
            });

            $table->editColumn('role', function ($row) {
                $labels = [];
                foreach ($row->role as $role) {
                    $labels[] = sprintf('<span class="role-label">%s</span>', strip_tags($role->name));
                }

                return implode(' ', $labels);
            });

            $table->editColumn('project', function ($row) {
                $labels = [];
                foreach ($row->projects as $project) {
                    $labels[] = sprintf('<span class="label">%s</span>', strip_tags($project->name));
                }

                return implode(' ', $labels);
            });

            $table->editColumn('status', function ($row) {
                if($row->id == 1){
                    return User::STATUS[$row->status];
                }else if ($row->status == '0' || $row->status) {
                    return view('widget.statusForm', [
                        'crudRoutePart' => $this->crudRoutePart,
                        'selection' => User::STATUS,
                        'selectionValue' => $row->status,
                        'id' => $row->id
                    ]);
                }
                return '';
            });

            $table->editColumn('type', function ($row) {
                if($row->type){
                    return User::TYPE[$row->type];
                }
                return '';
            });

            $table->editColumn('details', function ($row) {
                $roles = [];
                foreach ($row->role as $role) {
                    $roles[] = sprintf('<span class="role-label">%s</span>', strip_tags($role->name));
                }
                $projects = [];
                foreach ($row->projects as $project) {
                    $projects[] = sprintf('<span class="label">%s</span>', strip_tags($project->name));
                }
                $type = '';
                if($row->type){
                    $type = User::TYPE[$row->type];
                }
                return  '<b>用户名 : </b>' . strip_tags($row->username) . '<br>' .
                    '<b>角色 : </b>' . implode(' ', $roles) . '<br>' .
                    '<b>项目 : </b>' . implode(' ', $projects) . '<br>' .
                    '<b>分类 : </b>' . strip_tags($type) . '<br>';
            });

            $table->editColumn('details2', function ($row) {
                return '<b>最后登入ip : </b>' . strip_tags($row->last_ip) . '<br>' .
                    '<b>最后登入时间 : </b>' . strip_tags($row->last_login) . '<br>' .
                    '<b>创建时间 : </b>' . strip_tags($row->created_at) . '<br>' .
                    '<b>更新时间 : </b>' . strip_tags($row->updated_at);
                return '';
            });

            $table->editColumn('quest', function ($row) {
                if($row->isReviewer() || $row->isCoverer()){
                    return  '<b>每日任务 : </b>' .  strip_tags(USER::PRESS[$row->is_daily_press]) . '<br>' .
                        '<b>额外任务 : </b>' .  strip_tags(USER::PRESS[$row->is_extra_press]) . '<br>' .
                        '<b>每日任务数量 : </b>' . strip_tags($row->daily_quest) . '<br>' .
                        '<b>额外任务数量 : </b>' . strip_tags($row->extra_quest);
                }elseif($row->isUploader()){
                    return  '<b>每日任务数量 : </b>' . strip_tags($row->daily_quest2);
                }
                return '-';
            });

            $table->rawColumns(['actions', 'project', 'role', 'status','quest', 'details', 'details2']);

            return $table->make(true);
        }

        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "details" => ["name" => "详情", "sort" => 0],
                "details2" => ["name" => "详情", "sort" => 0],
                "quest" => ["name" => "任务", "sort" => 0],
                "username" => ["name" => "用户名", "visible" => 0],
                "last_ip" => ["name" => "最后登入ip", "visible" => 0],
                "last_login" => ["name" => "最后登入时间", "visible" => 0],
                "created_at" => ["name" => "创建时间", "visible" => 0],
                "updated_at" => ["name" => "更新时间", "visible" => 0],
                "status" => ["name" => '状态'],
                "role" => ["name" => "角色", "sort" => 0, "visible" => 0],
                'project' => ["name" => '项目', "sort" => 0, "visible" => 0],
                "type" => ["name" => '分类', "visible" => 0],
            ],
            'setting' => [
                'create' => 1,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' =>
                        [
                            'id' =>
                            [
                                'name' => 'id',
                                'type' => 'text',
                            ],
                            'role_id' =>
                            [
                                'name' => '角色',
                                'type' => 'select',
                                'route' => route('roles.select'),
                            ],
                            'project_id' =>
                            [
                                'name' => '项目',
                                'type' => 'select',
                                'route' => route('projects.select'),
                            ],
                            'status' => [
                                'name' => '状态',
                                'type' => User::STATUS,
                            ],
                        ]
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
                'username' => [
                    'name' => '用户名字',
                    'type' => 'text',
                    'required' => 1
                ],
                'password' => [
                    'name' => '密码',
                    'type' => 'password',
                    'required' => 1
                ],
                'role' => [
                    'name' => '角色',
                    'type' => 'select',
                    'route' => route('roles.select'),
                    'multiple' => 1,
                    'required' => 1
                ],
                'projects' => [
                    'name' => '项目',
                    'type' => 'select',
                    'route' => route('projects.select')
                ],
                'type' => [
                    'name' => '分类',
                    'type' =>  User::TYPE,
                    'value' => 3,
                    'required' => 1
                ],
                'ftp' => [
                    'name' => 'FTP',
                    'type' =>  'switch',
                    'required' => 1,
                    'value' => 0,
                    'setting' => [
                        'ftp_password' => [
                            'name' => '密码',
                            'type' => 'password',
                            'required' => 1
                        ]
                    ],
                    'condition' => [
                        'role' => 3,
                    ]
                ],
                'quest' => [
                    'name' => '任务',
                    'type' =>  'switch',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => 1,
                    'setting' => [
                        'daily_quest' => [
                            'name' => '每日任务数量',
                            'type' =>  'number',
                            'required' => 1,
                            'value' => 200
                        ],
                        'extra_quest' => [
                            'name' => '额外任务数量',
                            'type' => 'number',
                            'required' => 1,
                            'value' => 50
                        ]
                    ],
                    'condition' => [
                        'role' => '4,7',
                    ]
                ],
                'quest2' => [
                    'name' => '任务',
                    'type' =>  'switch',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => 1,
                    'setting' => [
                        'daily_quest2' => [
                            'name' => '每日任务数量',
                            'type' =>  'number',
                            'required' => 1,
                            'value' => 20
                        ],
                    ],
                    'condition' => [
                        'role' => 3,
                    ]
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
            DB::beginTransaction();
            if ($request->ftp == 'on') {
                if (!$request->ftp_password) {
                    return back()->withInput()->withErrors([
                        'msg' => 'ftp密码不能为空',
                    ]);
                }
            }
            $validate = $request->validate([
                'username' => ['required', Rule::unique('users', 'username')],
                'password' => ['required'],
                'role' => ['required'],
                'type' => ['required'],
                'daily_quest' => [],
                'daily_quest2' => [],
                'extra_quest' => []
            ], [
                'username.required' => '用户名不能为空',
                'password.required' => '密码不能为空',
                'role.required' => '角色不能为空',
                'username.unique' => '用户名已被使用',
                'type.required' => '分类不能为空',
            ]);
            $isEnglish = Helper::isEnglishName($validate['username']);
            if (!$isEnglish) {
                return back()->withInput()->withErrors([
                    'msg' => '用户名只能是英文',
                ]);
            }
            // Password will be hashed by model boot() method

            $user = User::create($validate);
            if ($request->ftp == 'on') {
                $path = Ftp::createFtp($validate['username'], $request->ftp_password);
                if (!$path) {
                    return back()->withInput()->withErrors([
                        'msg' => '在服务器创建ftp失败',
                    ]);
                }
                $data = [
                    'user_id' => $user->id,
                    'password' => $request->ftp_password,
                    'nickname' => $validate['username'],
                    'path' => $path,
                ];
                Ftp::create($data);
            }

            $user->projects()->sync($request->projects ?? []);
            $user->role()->sync($request->role ?? []);
            $user->clearRoleCache();
            User::processSaveLog($request->all(), $user, 1);

            DB::commit();
            return redirect()->route('users.index')->with('success', $this->title . '添加成功');
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
        $user = User::findOrFail($id);
        $readonly = 1;
        if (Auth::user()->isSuperAdmin()) {
            $readonly = 0;
        } else {
            $this->buttons = "";
        }
        $content = view('form', [
                'extra' => '',
            'edit' => 1,
            'id' => $id,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'username' => [
                    'name' => '用户名字',
                    'type' => 'text',
                    'value' => $user->username,
                    'required' => 1,
                    'readonly' => $readonly
                ],
                'password' => [
                    'name' => '密码',
                    'type' => 'password',
                    'value' => '',
                    'required' => 0
                ],
                'role' => [
                    'name' => '角色',
                    'multiple' => 1,
                    'type' => 'select',
                    'required' => 1,
                    'route' => route('roles.select'),
                    'value' => $user->role->pluck('id','id')->toArray(),
                    'label' => $user->role->pluck('name','id')->toArray(),
                    'readonly' => $readonly
                ],
                'projects' => [
                    'name' => '项目',
                    'type' => 'select',
                    'route' => route('projects.select'),
                    'value' => $user->projects->pluck('id')->toArray(),
                    'label' => $user->projects->pluck('name')->toArray(),
                    'readonly' => $readonly
                ],
                'type' => [
                    'name' => '分类',
                    'type' =>  User::TYPE,
                    'value' => $user->type,
                    'required' => 1
                ],
                'quest' => [
                    'name' => '任务',
                    'type' =>  'switch',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => 1,
                    'setting' => [
                        'daily_quest' => [
                            'name' => '每日任务数量',
                            'type' =>  'number',
                            'required' => 1,
                            'value' => $user->daily_quest,
                            'readonly' => !Auth::user()->isSuperAdmin()?1:0
                        ],
                        'extra_quest' => [
                            'name' => '额外任务数量',
                            'type' => 'number',
                            'required' => 1,
                            'value' => $user->extra_quest,
                            'readonly' => !Auth::user()->isSuperAdmin()?1:0
                        ]
                    ],
                    'condition' => [
                        'role' => '4,7',
                    ]
                ],
                'quest2' => [
                    'name' => '任务',
                    'type' =>  'switch',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => 1,
                    'setting' => [
                        'daily_quest2' => [
                            'name' => '每日任务数量',
                            'type' =>  'number',
                            'required' => 1,
                            'value' => $user->daily_quest2,
                        ],
                    ],
                    'condition' => [
                        'role' => 3,
                    ]
                ],
            ],
             
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
            $validate = $request->validate([
                'username' => ['required', Rule::unique('users', 'username')->ignore($id)],
                'password' => [],
                'role' => ['required'],
                'type' => ['required'],
                'daily_quest' => [],
                'daily_quest2' => [],
                'extra_quest' => []
            ], [
                'username.required' => '用户名不能为空',
                'role.required' => '角色不能为空',
                'username.unique' => '用户名已被使用',
                'type.required' => '分类不能为空',
            ]);
            if (Auth::user()->isSuperAdmin()) {
                $isEnglish = Helper::isEnglishName($validate['username']);
                if (!$isEnglish) {
                    return back()->withInput()->withErrors([
                        'msg' => '用户名只能是英文',
                    ]);
                }
            } else {
                unset($validate['username'], $validate['role_id'], $validate['daily_quest'], $validate['extra_quest']);
            }

            if (isset($validate['password'])) {
                // Password will be hashed by model boot() method
            } else {
                unset($validate['password']);
            }

            $user = User::findOrFail($id);
            $original = User::getManyRelationModel($user);
            $user->projects()->sync($request->projects ?? []);
            $user->role()->sync($request->role ?? []);
            $user->update($validate);
            $user->clearRoleCache();
            User::processSaveLog($request->all(), $user, 2, $original);
            DB::commit();
            return back()->with('success', $this->title . '编辑成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $user = User::findOrFail($id);
            if($user->last_login){
                return back()->withInput()->withErrors([
                    'msg' => '用户已登录无法删除',
                ]);
            }
            foreach ($user->ftps as $ftp) {
                $isDelete = Ftp::deleteFtp($ftp->server_id, $ftp->nickname);
                if (!$isDelete) {
                    return back()->withInput()->withErrors([
                        'msg' => '在服务器删除ftp失败',
                    ]);
                }
                $ftp->delete();
            }
            unset($user->ftps);
            $user->delete();
            $user->clearRoleCache();
            DB::commit();
            return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
        $user = User::findOrFail($id);
        $user->status = $request->get('status');
        $user->save();
        User::processSaveLog([], $user, 2, []);
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function show(string $id)
    {
        $user = User::findOrFail($id);
        if (!Auth::user()->checkUserRole([1, 2])) {
            if($user->id != Auth::user()->id){
                return back()->withErrors([
                    'msg' => '用户无权限查看别人的详情',
                ]);
            }
        }
        $columns = [
            'id' => [
                'name' => 'ID',
                'type' => 'text',
                'value' => $user->id,
            ],
            'username' => [
                'name' => '用户名字',
                'type' => 'text',
                'value' => $user->username,
            ],
            'role' => [
                'name' => '角色',
                'type' => 'multiple',
                'value' => $user->role->pluck('name')->toArray(),
            ],
            'projects' => [
                'name' => '项目',
                'type' => 'multiple',
                'value' => $user->projects->pluck('name')->toArray(),
            ],
            'type' => [
                'name' => '分类',
                'type' =>  'text',
                'value' => User::TYPE[$user->type],
            ],
            'last_ip' => [
                'name' => '最后登入ip',
                'type' =>  'text',
                'value' => $user->last_ip,
            ],
            'last_login' => [
                'name' => '最后登入时间',
                'type' =>  'text',
                'value' => $user->last_login,
            ],
            'created_at' => [
                'name' => '创建时间',
                'type' =>  'text',
                'value' => $user->created_at,
            ],
            'updated_at' => [
                'name' => '更新时间',
                'type' =>  'text',
                'value' => $user->updated_at,
            ],
        ];
        if($user->checkUserRole([4])){
            $totalVideoBeingAssigned = Video::where('assigned_to',$user->id)->count();
            $totalSuccessVideo = Video::where('assigned_to',$user->id)->where('status', 3)->count();
            $successRate = '-';
            if($totalVideoBeingAssigned){
                $successRate = number_format((float)($totalSuccessVideo / $totalVideoBeingAssigned) * 100, 2, '.', '')  . "%";
            }
            $columns['success_rate'] = [
                'name' => '审核成功率',
                'type' => 'text',
                'value' => $successRate,
            ];
            $columns['total_rereview'] = [
                'name' => '被提交重新审核次数',
                'type' => 'text',
                'value' => Video::where('assigned_to',$user->id)->whereNotNull('rereviewer_by')->count(),
            ];
            $columns['quest_hr'] = [
                'type' => 'hr',
                'value' => '任务',
            ];
            $columns['is_daily_press'] = [
                'name' => '每日任务',
                'type' =>  'text',
                'value' => USER::PRESS[$user->is_daily_press],
            ];
            $columns['is_extra_press'] = [
                'name' => '额外任务',
                'type' =>  'text',
                'value' => USER::PRESS[$user->is_extra_press],
            ];
            $columns['daily_quest'] = [
                'name' => '每日任务数量',
                'type' =>  'text',
                'value' => $user->daily_quest,
            ];
            $columns['extra_quest'] = [
                'name' => '额外任务数量',
                'type' =>  'text',
                'value' => $user->extra_quest,
            ];
            $columns['extra_quest'] = [
                'name' => '额外任务数量',
                'type' =>  'text',
                'value' => $user->extra_quest,
            ];
            $temp = [];
            foreach(TokenLogs::TYPE as $key=>$value){
                $temp[$value] = TokenLogs::where('user_id',$user->id)->where('type',$key)->count();
            }
            $columns['badge'] = [
                'name' => '徽章',
                'type' =>  'json',
                'value' => $temp,
            ];
        }else{
            $totalVideoBeingAssigned = Video::where('uploader',$user->id)->count();
            $totalSuccessVideo = Video::where('uploader',$user->id)->where('status', 3)->count();
            $successRate = '-';
            if($totalVideoBeingAssigned){
                $successRate = number_format((float)($totalSuccessVideo / $totalVideoBeingAssigned) * 100, 2, '.', '')  . "%";
            }
            $columns['success_rate'] = [
                'name' => '上传成功率',
                'type' => 'text',
                'value' => $successRate,
            ];
        }

        return view('view', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'backButton' => $this->buttons,
            'button' => '',
            'columns' => $columns
        ]);
    }

    public function selectCoverer(Request $request)
    {
        return $this->baseSelect($request, $this->model::query()->whereHas('role', function ($query) {
            $query->where('id', 7);
        }));
    }

    public function selectReviewer(Request $request)
    {
        return $this->baseSelect($request, $this->model::query()->whereHas('role', function ($query) {
            $query->where('id', 4);
        }));
    }

    public function selectUploader(Request $request)
    {
        return $this->baseSelect($request, $this->model::query()->whereHas('role', function ($query) {
            $query->where('id', 3);
        }));
    }

    public function selectUserProject(Request $request)
    {
        return $this->baseSelect($request, $this->model::query()->whereHas('projects', function ($query) {
            $query->where('projects.id', Project::MINGSHUN);
        }));
    }
}
