<?php

namespace App\Http\Controllers;

use App\Models\Ftp;
use App\Models\Photo;
use App\Models\PhotoProjectRule;
use App\Models\Video;
use App\Trait\Import;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PhotoController extends Controller
{
    use Import;
    public function __construct()
    {
        $this->init(Photo::class);
        parent::__construct();
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Photo::search($request)->select(sprintf('%s.*', (new Photo())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                if(Auth::user()->isSuperAdmin()){
                    $delete = 1;
                }else{
                    $delete = 0;
                }
                return view('widget.actionButtons', [
                    'crudRoutePart' => $this->crudRoutePart,
                    'row' => $row,
                    'edit' => 0,
                    'delete' => $delete,
                    'isButton' => 1
                ]);
            });

            $table->editColumn('status', function ($row) {
                if ($row->status == '0' || $row->status) {
                    return Photo::STATUS[$row->status] ?? '';
                }
                return '';
            });

            $table->editColumn('path', function ($row) {
                if($row->path){
                    if( $row->server_id){
                        $cover_photo = $row->servers->play_domain . "/public" . $row->path;
                    }else{
                        $cover_photo = $row->path;
                    }
                    return '<img src="' . $cover_photo . '" class="table-img clickable-img">';
                }else{
                    return '<img src="' . asset('picture/no-image.png') . '" class="table-img">';
                }
                return '';
            });

            $table->editColumn('detail', function ($row) {
                $text = '';
                if ($row->uploader == '0') {
                    $text .= '<b>创建者 : </b>' . strip_tags("接口传入") . '<br>' . '<b>创建时间 : </b>' . $row->created_at . '<br>';
                } else {
                    $text .= '<b>创建者 : </b>' . strip_tags($row->uploaderUser?->username) . '<br>'. '<b>创建时间 : </b>' . $row->created_at . '<br>';
                }
                if ($row->photo_project_rule_id) {
                    $text .= '<b>图片规则 : </b>' . strip_tags($row->photoRule?->name) . '<br>';
                }
                if(Auth::user()->isSuperAdmin()){
                    $text .= '<b>项目 : </b>' . strip_tags($row->projects?->name) . '<br>';
                }
                $text .= '<b>图片同步链接 : </b>' . strip_tags($row->save_path) . '<br>';
                return $text;
            });

            $table->rawColumns(['actions','path','detail']);

            return $table->make(true);
        }

        $user = Auth::user();
        $projects = $user->projects->first();
        if (!Auth::user()->checkUserRole([1, 2])) {
            if (!$projects) {
                return back()->withErrors([
                    'msg' => '用户没项目，无法进入预选区',
                ]);
            }
        }
        $title = $this->title . '（' . ($projects->name ?? '全') . '）';

        $user_id = Auth::user()->id;
        $serverIdArray = Ftp::where('user_id', $user_id)->pluck('server_id')->toArray();
        if (empty($serverIdArray)) {
           $create = 0;
        }else{
            $create = 1;
        }

        $content = view('index', [
            'title' => $title,
            'crudRoutePart' => $this->crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "title" => ["name" => "名字"],
                "path" => ["name" => "图片"],
                "status" => ["name" => "状态"],
                "detail" => ["name" => "详情"],
            ],
            'setting' => [
                'create' => $create,
                'imageModal' => view('widget.imageModal', [
                    'script' => 0,
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
                                'type' => Photo::STATUS,
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
        $user_id = Auth::user()->id;
        $serverIdArray = Ftp::where('user_id', $user_id)->pluck('server_id')->toArray();
        if (empty($serverIdArray)) {
            return back()->withErrors([
                'msg' => '用户没有ftp账号,不能创建图片',
            ]);
        }
        $projects = Auth::user()->projects->first();
        if (empty($projects)) {
            return back()->withErrors([
                'msg' => '用户没项目,不能创建图片',
            ]);
        }
        $content = view('form', [
            'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'title' => [
                    'name' => '名字',
                    'type' => 'text',
                    'required' => 1
                ],
                'image'=>[
                    'name' => '图片',
                    'type' => 'file',
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
                'photo_project_rule_id' => [
                    'name' => '图片规则',
                    'type' => PhotoProjectRule::active()->where('project_id', $projects->id)->pluck('name', 'id')->toArray(),
                    'required' => 1,
                ],
                'output_dir' => [
                    'name' => '储存链接',
                    'type' => 'text',
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
        try{
            DB::beginTransaction();

            $validate = $request->validate([
                'title' => ['required'],
                'photo_project_rule_id' => ['required'],
                'image' => [],
                'output_dir' => [],
            ], [
                'title.required' => '名字不能为空',
                'photo_project_rule_id.required' => '图片规则不能为空',
            ]);
            $uploadedFile = $validate['image'] ?? null;
            if ($uploadedFile) {
                unset($validate['image']);
            }else{
                return back()->withErrors([
                    'msg' => '用户没上传图片',
                ]);
            }
            if($validate['output_dir']){
                $output_dir = '/' . $validate['output_dir'];
            }else{
                $output_dir = '';
            }
           
            unset($validate['output_dir']);
            $user = Auth::user();
            $projects = $user->projects->first();
            $validate['project_id'] = $projects->id;
            $validate['uploader'] = $user->id;
            $validate['status'] = 1;
            $validate['path'] = $uploadedFile;

            $photo = Photo::create($validate);
            if ($uploadedFile) {
                $image_name = time() . '_' ."imageWatermark" . $photo->id . ".png";
                $savePath = $projects->name . $output_dir. "/".$image_name;
                list($response,$coverNewPath) = Photo::addWatermarkImage(asset($uploadedFile),$validate['photo_project_rule_id'],$savePath ,$validate['project_id']);
                if (!$response) {
                    return back()->withInput()->withErrors([
                        'msg' => '图片'.$coverNewPath,
                    ]);
                }
                $path ='public'. str_replace("/storage","",$uploadedFile);
                $watermark_image = 'photoWatermark/'. $photo->id . "/" . $image_name;
                $newPath ='public/'. $watermark_image;
                Storage::move($path, $newPath);
                $photo->path = '/storage/' . $watermark_image;
                $photo->save_path = $savePath;
                $photo->status = 3;
                $photo->save();
            }
            
            DB::commit();
            return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '添加成功');
       
        }catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return back()->withErrors([
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
        $photo = Photo::find($id);
        $temp_ori_image = str_replace("/storage","",$photo->path);
        Storage::delete('public'.$temp_ori_image);
        $photo->delete();
        return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
