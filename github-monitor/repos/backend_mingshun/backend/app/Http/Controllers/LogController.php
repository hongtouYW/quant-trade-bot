<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use DataTables;

class LogController extends Controller
{
    public $title = '操作日志';
    public $crudRoutePart = 'logs';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Log::with(['createdUser'])->search($request)->select(sprintf('%s.*', (new Log())->getTable()));
            $table = Datatables::of($query);

            $table->editColumn('type', function ($row) {
                return Log::TYPE[$row->type];
            });


            $table->editColumn('createdUser.username', function ($row) {
                if ($row->user == '0') {
                    return "接口传入";
                }
                return $row->createdUser->username;
            });

            $table->editColumn('data', function ($row) {
                $temp = '';
                foreach(json_decode($row->data) as $key => $data){
                    $temp .= "<b>" . strip_tags($key) . " : </b> " . strip_tags($data) . "<br>";
                }
                return $temp;
            });

            $table->editColumn('action', function ($row) {
                if($row->target_id){
                    $temp = " (" . $row->target_id . ")";
                }else{
                    $temp = '';
                }
                $href = '';
                if(Log::ROUTE[$row->model] ?? ''){
                    $href = "target='_blank' href='".route(Log::ROUTE[$row->model] . '.update',$row->target_id)."'";
                }
                return Log::TYPE[$row->type] . " <a ". $href .">" . Log::MODEL[$row->model] . "</a>" . $temp;
            });

            $table->rawColumns(['data','action']);

            return $table->make(true);
        }
        $content = view('index', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "data" => ["name" => "资料", 'width' => '300px','className'=>'details'],
                "createdUser.username" => ["name" => "用户"],
                "target_id" => ["name" => "id", "visible" => 0],
                "type" => ["name" => "名字", "visible" => 0],
                "model" => ["name" => "Model", "visible" => 0],
                "action" => ["name" => "行动", "sort" => 0],
                "ip" => ["name" => "IP"],
                "created_at" => ["name" => "时间"],
            ],
            'setting' => [
                'create' => 0,
                'actions' => 0,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' => [
                            'model' =>
                            [
                                'name' => '模型',
                                'type' => Log::MODEL,
                            ],
                            'target_id' =>
                            [
                                'name' => '目标id',
                                'type' => 'text',
                            ],
                            'user' =>
                            [
                                'name' => '用户',
                                'type' => 'select',
                                'route' => route('users.select'),
                                'init' => array(0 => [
                                    'id' => 0,
                                    'text' => "接口传入"
                                ])
                            ],
                            'created_at' => [
                                'name' => '时间',
                                'type' => 'date2',
                            ]
                        ]
                    ]
                ),
            ],
        ]);

        return view('template', compact('content'));
    }
}
