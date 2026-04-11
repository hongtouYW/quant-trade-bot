<?php

namespace App\Http\Controllers;

use App\Models\PostChoose;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Models\VideoChoose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DataTables;

class PostChooseController extends Controller
{
    public function __construct()
    {
        $this->init(PostChoose::class);
        parent::__construct();
    }

    public function historyIndex(Request $request)
    {
        $request->merge(['history' => 1]);
        return $this->index($request);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $projects = $user->projects->first();
        if (!$user->isSuperAdmin()) {
            if (!$projects) {
                return back()->withErrors([
                    'msg' => '用户没项目，无法进入预选区',
                ]);
            }
        }
        if ($request->ajax()) {
            $query = PostChoose::search($request)->select(sprintf('%s.*', (new PostChoose())->getTable()));
            $table = DataTables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                return PostChoose::getTableRowAction($row, $this->crudRoutePart, 1);
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return PostChoose::STATUS[$row->status];
                }
                return '';
            });

            $table->editColumn('details', function ($row) {
                $tag_labels = [];
                foreach ($row->post->tags as $tag) {
                    $tag_labels[] = sprintf('<span class="label">%s</span>', strip_tags($tag->name));
                }
                $type_labels = [];
                foreach ($row->post->types as $type) {
                    $type_labels[] = '<span class="label2">' . strip_tags($type->name) . '</span>';
                }
                return  '<b>帖子id : </b>' . strip_tags($row->post->id) . '<br>' .
                    '<b>uid : </b>' . strip_tags($row->post->uid) . '<br>' .
                    '<b>标题 : </b>' . strip_tags($row->post->title) . '<br>' .
                    '<b>来源 : </b>' . strip_tags($row->post->source) . '<br>' .
                    '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
                    '<b>分类 : </b>' . implode(' ', $type_labels) . '<br>' ;
            });

            $table->editColumn('created', function ($row) {
                $user = $row->user;
                $created_by = "";
                if ($user) {
                    $created_by = $user->username ?? '';
                }
                $temp = '<b>预选者 : </b>' . strip_tags($created_by) . '<br>' .
                    '<b>预选时间 : </b>' . strip_tags($row->created_at);
                if ($row->return_msg) {
                    $temp .=   '<br><b>发送返回信息 : </b>' . strip_tags($row->return_msg);
                }
                return $temp;
            });

            $table->editColumn('project.name', function ($row) {
                $project = $row->project;
                if ($project) {
                    return $project->name ?? '';
                }
                return '';
            });

            $table->editColumn('uid', function ($row) {
                return $row->post->uid;
            });

            $table->editColumn('title', function ($row) {
                return $row->post->title;
            });

            $table->rawColumns(['actions', 'details', "created", "tag", "type"]);

            return $table->make(true);
        }


        $filters = [
            'id' =>
            [
                'name' => 'id',
                'type' => 'text',
            ],
            'tag' => [
                'name' => '标签',
                'type' => 'select',
                'route' => route('tags.select'),
            ],
            'type' => [
                'name' => '分类',
                'type' => 'select',
                'route' => route('types.select'),
            ],
            'created_by' => [
                'name' => '预选者',
            ],
        ];

        if($request->history){
            $status_array = PostChoose::STATUS;
            array_shift($status_array);
            $filters['status'] = [
                'name' => '状态',
                'type' => $status_array,
            ];
        }

        if (Auth::user()->isSuperAdmin()) {
            $filters['created_by'] = [
                'name' => '预选者',
                'type' => 'select',
                'route' => route('users.select'),
            ];
            $filters['project_id'] = [
                'name' => '项目',
                'type' => 'select',
                'route' => route('projects.select'),
            ];
        } else {
            $filters['created_by'] = [
                'name' => '预选者',
                'type' => $projects->users->pluck('username', 'id')->toArray(),
            ];
        }

        $ruleValue = [];
        $catValue =[];

        $columns = [
            "id" => ["name" => "ID"],
            "details" => ["name" => '详情', 'sort' => 0, 'width' => '300px'],
            "status" => ["name" => '状态'],
            "created" => ["name" => '预选者', 'sort' => 0],
            "project.name" => ["name" => '项目'],
            "uid" => ["name" => 'UID', 'visible' => 0, 'sort' => 0],
            "title" => ["name" => '标题', 'visible' => 0, 'sort' => 0],
        ];

        if (($projects->name ?? "")) {
            unset($columns['project.name']);
        }
        if ($request->history) {
            $title = '帖子预选历史（' . ($projects->name ?? '全') . '）';
            $crudRoutePart = "postsChooseHistory";
        } else {
            $title = '帖子预选区（' . ($projects->name ?? '全') . '）';
            $crudRoutePart = 'postsChoose';
        }
        $content = view('index', [
            'title' => $title,
            'crudRoutePart' => $crudRoutePart,
            'columns' =>  $columns,
            'setting' => [
                'create' => 0,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => $filters
                    ]
                ),
                'cutStatus' => view('widget.cutModal', [
                    "modalBtnClass" => PostChoose::CUT_BTN,
                    'crudRoutePart' => $this->crudRoutePart . '.cutStatus',
                    'value' => 2,
                    "ruleValue" => $ruleValue,
                    "catValue" => $catValue['cat'] ?? $catValue,
                    "subcatValue" => $catValue['subcat'] ?? []
                ]),
                'cutStatusBtn' => PostChoose::CUT_BTN,
            ],
        ]);

        return view('template', compact('content'));
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $postChoose = PostChoose::find($id);
            $status = $request->get('status');
            switch ($status) {
                case 0:
                    $postChoose->delete();
                    break;
                case 2:
                    $postChoose->status = 2;
                    $postChoose->save();
                    break;
            }
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function cutStatus(Request $request)
    {
        try {
            $ids = json_decode($request->get('id'), true);
            if(gettype($ids) == 'array'){
                foreach($ids as $id){
                    PostChoose::cutStatus($request, $id);
                }
            }else{  
                PostChoose::cutStatus($request, $ids);
            }
            $request->replace(['id' => $request->get('id')]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
