<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\PostVideo;
use App\Models\Server;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use CURLFile;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function __construct()
    {
        $this->init(Post::class);
        parent::__construct();
    }

    public function failedIndex(Request $request)
    {
        $request->merge(['fail' => 1]);
        return $this->index($request);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Post::search($request)->select(sprintf('%s.*', (new Post())->getTable()));
            $table = Datatables::of($query);

            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                return Post::getTableRowAction($row, $this->crudRoutePart);
            });

            $table->editColumn('details', function ($row) {
                $tag_labels = [];
                foreach ($row->tags as $tag) {
                    $tag_labels[] = sprintf('<span class="label">%s</span>', strip_tags($tag->name));
                }
                $type_labels = [];
                foreach ($row->types as $type) {
                    $type_labels[] = '<span class="label2">' . strip_tags($type->name) . '</span>';
                }
                return  '<b>uid : </b>' . strip_tags($row->uid) . '<br>' .
                    '<b>标题 : </b>' . strip_tags($row->title) . '<br>' .
                    '<b>来源 : </b>' . strip_tags($row->source) . '<br>' .
                    '<b>标签 : </b>' . implode(' ', $tag_labels) . '<br>' .
                    '<b>分类 : </b>' . implode(' ', $type_labels) . '<br>' ;
            });

            $table->editColumn('created_at', function ($row) {
                if ($row->uploader == '0') {
                    $uploader = "接口传入";
                } else {
                    $uploader = $row->uploaderUser?->username;
                }

                return  '<b>创建者 : </b>' . strip_tags($uploader) . '<br>' .
                    '<b>创建时间 : </b>' . strip_tags($row->created_at);
            });

            $table->editColumn('approved', function ($row) {
                $first_approved_by = "";
                if ($row->first_approved_by) {
                    $first_approved_by = $row->firstApprovedByUser->username;
                }
                if ($row->reason) {
                    $text = '<b>审核不通过人 : </b>' . strip_tags($first_approved_by) . '<br>' .
                        '<b>审核不通过时间 : </b>' . strip_tags($row->first_approved_at) . '<br>' .
                        '<b>审核不通过理由 : </b>' . strip_tags($row->reason);
                } else {
                    $text = '<b>审核人 : </b>' . strip_tags($first_approved_by) . '<br>' .
                        '<b>审核时间 : </b>' . strip_tags($row->first_approved_at) . '<br>';
                }
                return $text;
            });

            $table->editColumn('uploader', function ($row) {
                if ($row->uploader == '0') {
                    return "接口传入";
                }
                return $row->uploaderUser->username;
            });

            $table->editColumn('status', function ($row) {
                return Post::STATUS[$row->status];
            });

            $table->rawColumns(['actions','details','cover_photo_html', 'created_at', 'approved']);

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
            'approved_by' => [
                'name' => '审核者',
                'type' => 'select',
                'route' => route('users.reviewer.select'),
            ]
        ];

        if (!Auth::user()->checkUserRole([5, 6]) && !$request->fail) {
            $filters['status'] = [
                'name' => '状态',
                'type' => Post::STATUS
            ];
        }

        if (!Auth::user()->checkUserRole([3, 5, 6])) {
            $filters['uploader'] = [
                'name' => '创建者',
                'type' => 'select',
                'route' => route('users.uploader.select'),
                'init' => array(0 => [
                    'id' => 0,
                    'text' => "接口传入"
                ])
            ];
        }
        
        if(Auth::user()->checkUserRole([1])){
            $filters['created_at'] = [
                'name' => '创建时间',
                'type' => 'date2',
            ];
    
            $filters['first_approved_at'] = [
                'name' => '审核',
                'type' => 'date2',
            ];
        }

        if ($request->fail) {
            $crudRoutePart = 'postsFailed';
            $title = '审核失败帖子';
        } else {
            $crudRoutePart = $this->crudRoutePart;
            $title = $this->title;
        }

        $multiSelectButton = [];
        if (Auth::user()->checkUserRole([1, 5, 6]) && !$request->fail) {
            $multiSelectButton['pre-select'] = [
                'name' => "预选",
                'status' => 5,
                'statusNow' => Post::STATUS[3],
                'route' => route('posts.massChangeStatus'),
                'confirm' => 0
            ];
        }
        if (Auth::user()->checkUserRole([1, 2, 4]) && !$request->fail) {
            $multiSelectButton['approved'] = [
                'name' => '通过<i class="bx bx-check"></i>',
                'status' => 3,
                'statusNow' => Post::STATUS[1] . "," . Post::STATUS[2],
                'route' => route('posts.massChangeStatus')
            ];
        }
        $content = view('index', [
            'title' => $title,
            'crudRoutePart' => $crudRoutePart,
            'columns' => [
                "id" => ["name" => "ID"],
                "details" => ["name" => '详情', 'sort' => 0, 'width' => '300px'],
                "uid" => ["name" => "编码", 'visible' => 0],
                "title" => ["name" => '标题', 'visible' => 0],
                "source" => ["name" => '来源', 'visible' => 0],
                "status" => ["name" => "状态"],
                "uploader" => ["name" => '创建者', 'visible' => 0, 'sort' => 0],
                "approved" => ["name" => '审核', 'sort' => 0, 'width' => '200px'],
                "created_at" => ["name" => '创建时间'],
            ],
            'setting' => [
                'create' => 0,
                'filters' => view(
                    'widget.dataTableFilter',
                    [
                        'filters' =>  $filters
                    ]
                ),
                'rejectStatus' => view('widget.rejectModal', [
                    'script' => 0,
                    "modalBtnClass" => Post::REJECT_BTN,
                    'crudRoutePart' => $this->crudRoutePart . '.changeStatusModal',
                    'value' => 4
                ]),
                'rejectStatusBtn' => Post::REJECT_BTN,
                'multiSelect' => [
                    'button' => $multiSelectButton,
                ],
            ],
        ]);

        return view('template', compact('content'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $randomString = bin2hex(random_bytes(4));
        $processId = getmypid();
        $uid = uniqid($randomString . $processId);
        $content = view('form', [
                'extra' => '',
            'edit' => 0,
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'buttons' => $this->buttons,
            'columns' => [
                'uid' => [
                    'name' => '编码',
                    'type' => 'text',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => $uid
                ],
                'title' => [
                    'name' => '标题',
                    'type' => 'text',
                    'required' => 1
                ],
                'description' => [
                    'name' => '描述',
                    'type' => 'textarea',
                    'required' => 1,
                ],
                'images'=>[
                    'name' => '图片',
                    'type' => 'file',
                    'required' => 1,
                    'multiple' => 1,
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
                'videos' => [
                    'name' => '视频',
                    'type' => 'file',
                    'required' => 1,
                    'multiple' => 1,
                    'setting' => [
                        'type' => 'video',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
                'source' => [
                    'name' => '来源',
                    'type' => 'text',
                    'required' => 0
                ],
                'types' => [
                    'name' => '分类',
                    'type' => 'select',
                    'required' => 0,
                    'route' => route('types.select')
                ],
                'tags' => [
                    'name' => '标签',
                    'type' => 'select',
                    'required' => 0,
                    'multiple' => 1,
                    'route' => route('tags.select')
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
            'uid' => ['required', Rule::unique('posts', 'uid')],
            'title' => ['required'],
            'description' => ['required'],
            'source' => [],
            'tags' => [],
            'types' => [],
            'id_videos'=>['required'],
            'id_images'=>['required'],
        ], [
            'uid.required' => '编码不能为空',
            'uid.unique' => '编码已被使用',
            'title.required' => '标题不能为空',
            'description.required' => '描述不能为空',
            'id_videos.required' => '视频不能为空',
            'id_images.required' => '图片不能为空',
        ]);
        unset($validate['id_images'],$validate['id_videos']);
        $image_results=[];
        foreach($request->images as $image){
            $file = new CURLFile(public_path($image));
            $image_results[] = Post::sendFile($request, 1, $file, $validate['uid']);
            $image = str_replace("/storage","/",$image);
            Storage::delete('public' . $image);
        }
        $video_results=[];
        foreach($request->videos as $video){
            $file = new CURLFile(public_path($video));
            $video_results[] = Post::sendFile($request, 2, $file, $validate['uid']);
            $video = str_replace("/storage","/",$video);
            Storage::delete('public' . $video);
        }
        $file_path = $validate['uid'] . '_description.txt';
        file_put_contents($file_path, $validate['description']);
        $file = new CURLFile($file_path);
        $validate['description'] = Post::sendFile($request, 4, $file, $validate['uid']);
        $validate['uploader'] = Auth::user()->id;
        $validate['status'] = 1;
        $validate['server_id'] = Server::where('post_recommended', 1)->first()->id ?? 0;
        $post = Post::create($validate);
        foreach($image_results as $image_result){
            PostImage::create([
                'path' => $image_result,
                'post_id' => $post->id
            ]);
        }
        foreach($video_results as $video_result){
            PostVideo::create([
                'path' => $video_result,
                'post_id' => $post->id
            ]);
        }
        $post->tags()->sync($validate['tags'] ?? []);
        $post->types()->sync($validate['types'] ?? []);
        Post::processSaveLog($request->all(), $post, 1);
        unlink($file_path);
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
        $post = Post::findOrFail($id);
        $server = $post->servers;
        $post_images=[];
        foreach($post->images as $image){
            $post_images[] = [
                'src' => $server->play_domain . $image->path,
                'id' => $image->id
            ];
        }
        $post_videos=[];
        foreach($post->videos as $video){
            $post_videos[] = [
                'src' => $server->play_domain . $video->path,
                'id' => $video->id
            ];
        }
        try{
            $context = stream_context_create([
                'http' => [
                    'header' => "Referer: http://54.151.229.96:8088/"
                ]
            ]);    
            $description_content = file_get_contents($server->play_domain . $post->description, false, $context);
        }catch(\Exception $e){
            return back()->withErrors([
                'msg' => '读取描述失败',
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
                'uid' => [
                    'name' => '编码',
                    'type' => 'text',
                    'required' => 1,
                    'readonly' => 1,
                    'value' => $post->uid
                ],
                'title' => [
                    'name' => '标题',
                    'type' => 'text',
                    'required' => 1,
                    'value' => $post->title
                ],
                'description' => [
                    'name' => '描述',
                    'type' => 'textarea',
                    'required' => 1,
                    'value' => $description_content,
                ],
                'images'=>[
                    'name' => '图片',
                    'type' => 'file',
                    'required' => 1,
                    'multiple' => 1,
                    'value' =>  $post_images,
                    'setting' => [
                        'type' => 'image',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
                'videos' => [
                    'name' => '视频',
                    'type' => 'file',
                    'required' => 1,
                    'multiple' => 1,
                    'value' =>  $post_videos,
                    'setting' => [
                        'type' => 'video',
                        'tempUploadUrl' => route('tempUpload')
                    ]
                ],
                'source' => [
                    'name' => '来源',
                    'type' => 'text',
                    'required' => 0,
                    'value' => $post->source,
                ],
                'types' => [
                    'name' => '分类',
                    'type' => 'select',
                    'required' => 1,
                    'route' => route('types.select'),
                    'value' => $post->types->pluck('id')->toArray(),
                    'label' => $post->types->pluck('name')->toArray(),
                ],
                'tags' => [
                    'name' => '标签',
                    'multiple' => 1,
                    'type' => 'select',
                    'multiple' => 1,
                    'required' => 0,
                    'route' => route('tags.select'),
                    'value' => $post->tags->pluck('id')->toArray(),
                    'label' => $post->tags->pluck('name','id')->toArray(),
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
            'title' => ['required'],
            'description' => ['required'],
            'source' => [],
            'tags' => [],
            'types' => [],
            'id_videos'=>['required'],
            'id_images'=>['required'],
        ], [
            'title.required' => '标题不能为空',
            'description.required' => '描述不能为空',
            'id_videos.required' => '视频不能为空',
            'id_images.required' => '图片不能为空',
        ]);
        $post = Post::findOrFail($id);
        unset($validate['id_images'],$validate['id_videos']);
        if(isset($request->id_images) && isset($request->images)){
            $post_images_id_array =  $post->images->pluck('id')->toArray();
            $id_images = $request->id_images;
            foreach($request->images as $key=>$image){
                if($request->id_images[$key] == 0){
                    $file = new CURLFile(public_path($image));
                    $image_path = Post::sendFile($request, 1, $file, $post->uid);
                    $image = str_replace("/storage","/",$image);
                    Storage::delete('public' . $image);
                    PostImage::create([
                        'path' => $image_path,
                        'post_id' => $post->id
                    ]);
                }else{
                    $key = array_search($id_images[$key], $post_images_id_array);
                    if ($key !== false) {
                        unset($post_images_id_array[$key]);
                    }
                }
            }
        }
        if(isset($request->id_videos) && isset($request->videos)){
            $post_videos_id_array =  $post->videos->pluck('id')->toArray();
            $id_videos = $request->id_videos;
            foreach($request->videos as $key=>$video){
                if($request->id_videos[$key] == 0){
                    $file = new CURLFile(public_path($video));
                    $video_path = Post::sendFile($request, 2, $file, $post->uid);
                    $video = str_replace("/storage","/",$video);
                    Storage::delete('public' . $video);
                    PostVideo::create([
                        'path' => $video_path,
                        'post_id' => $post->id
                    ]);
                }else{
                    $key = array_search($id_videos[$key], $post_videos_id_array);
                    if ($key !== false) {
                        unset($post_videos_id_array[$key]);
                    }
                }
            }
        }
        foreach($post_images_id_array as $post_image_id){
            $postImage = PostImage::find($post_image_id);
            Post::deleteFile($post->id,$post->uid,$postImage->path);
            $postImage->delete();
        }
        foreach($post_videos_id_array as $post_video_id){
            $postVideo = PostVideo::find($post_video_id);
            Post::deleteFile($post->id,$post->uid,$postVideo->path);
            $postVideo->delete();
        }
        $file_path = $post->uid . '_description.txt';
        file_put_contents($file_path, $validate['description']);
        $file = new CURLFile($file_path);
        Post::sendFile($request, 4, $file, $post->uid);
        unset($validate['description']);
        $original = Post::getManyRelationModel($post);
        $post->tags()->sync($validate['tags'] ?? []);
        $post->types()->sync($validate['types'] ?? []);
        $post->update($validate);
        Post::processSaveLog($request->all(), $post, 2, $original);
        unlink($file_path);
        return redirect()->route($this->crudRoutePart . '.index')->with('success', $this->title . '编辑成功');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function show(string $id)
    {
        $post = Post::findOrFail($id);
        $server = $post->servers;
        try{
            $context = stream_context_create([
                'http' => [
                    'header' => "Referer: " . $server->play_domain
                ]
            ]);    
            $description_content = file_get_contents($server->play_domain . $post->description, false, $context);
        }catch(\Exception $e){
            return back()->withErrors([
                'msg' => '读取描述失败',
            ]);
        }
        $post_images=[];
        foreach($post->images as $image){
            $post_images[] = $server->play_domain . $image->path;
        }
        $post_videos=[];
        foreach($post->videos as $video){
            $post_videos[] = $server->play_domain . $video->path;
        }
        $columns = [
            'id' => [
                'name' => 'ID',
                'type' => 'text',
                'value' => $post->id,
            ],
            'uid' => [
                'name' => '编码',
                'type' => 'text',
                'value' => $post->uid,
            ],
            'title' => [
                'name' => '标题',
                'type' => 'text',
                'value' => $post->title,
            ],
            'description' => [
                'name' => '描述',
                'type' => 'text',
                'value' => $description_content
            ],
            'image' => [
                'name' => '图片',
                'type' =>  'image',
                'value' => $post_images,
            ],
            'video' => [
                'name' => '视频',
                'type' => 'video',
                'value' =>  $post_videos,
            ],
            'tag' => [
                'name' => '标签',
                'type' =>  'multiple',
                'value' => $post->tags->pluck('name')->toArray(),
            ],
            'type' => [
                'name' => '分类',
                'type' =>  'multiple',
                'value' => $post->types->pluck('name')->toArray(),
            ],
            'status' => [
                'name' => '状态',
                'type' => 'text',
                'value' => Post::STATUS[$post->status],
            ],
            'source' => [
                'name' => '来源',
                'type' => 'text',
                'value' => $post->source,
            ],
            'server_id' => [
                'name' => '服务器',
                'type' => 'text',
                'value' => $post->servers->name . ' (' . $post->servers->ip . ')',
            ],
            'first_approved_by' => [
                'name' => '审核人',
                'type' => 'text',
                'value' => $post->firstApprovedByUser->username ?? '',
            ],
            'first_approved_at' => [
                'name' => '审核时间',
                'type' => 'text',
                'value' => $post->first_approved_at,
            ],
            'reason' => [
                'name' => '不通过理由',
                'type' => 'text',
                'value' => $post->reason,
            ],
        ];
        $postChooseArray = [];
        foreach($post->chooseProject as $postChoose){
            $postChooseArray[] = [
                'project' => $postChoose->project->name ?? '',
                'choose_by'=> $postChoose->user->username ?? '',
                'choose_at'=> $postChoose->created_at ?? '',
                
            ];
        }
        if(!empty($postChooseArray)){
            array_unshift($postChooseArray,[
                'project' => '预选项目',
                'choose_by'=> '预选者',
                'choose_at'=> '预选时间',
            ]);
            $columns['post_choose'] = [
                'name' => '预选',
                'type' => 'table',
                'value' => $postChooseArray,
            ];
        }

        $columns['others'] = [
            'name' => '其他',
            'type' => 'json',
            'value' => json_decode($post->others),
        ];

        $backButton = $this->buttons;
        if (Auth::user()->checkUserRole([1, 5, 6])) {
            $backButton .= view('widget.backButton', [
                'title' => '预选区',
                'crudRoutePart' => 'postsChoose',
            ]);
        }

        return view('view', [
            'title' => $this->title,
            'crudRoutePart' => $this->crudRoutePart,
            'backButton' => $backButton,
            'button' => '',
            'columns' => $columns
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
        $post = Post::find($id);
        $uid = $post->uid;
        if (Auth::user()->id == 3 && $post->uploader != Auth::user()->id) {
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '用户无权限删除别人的帖子',
            ]);
        }
        if($post->status!=1){
            return redirect()->route($this->crudRoutePart . '.index')->withErrors([
                'msg' => '无法删除帖子',
            ]);
        }
        foreach($post->postTypes as $type){
            $type->delete();
        }
        foreach($post->postTags as $tag){
            $tag->delete();
        }
        unset($post->uploader,$post->postTypes,$post->postTags);
        $post->delete();
        Storage::deleteDirectory('public/posts/editor/'.$uid);
        return back()->with('success', $this->title . '删除成功');
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    public function changeStatusModal(Request $request)
    {
        try {
            $id = $request->get('id');
            $reason = $request->get('reason');
            $status = $request->get('status');
            $video = Post::findOrFail($id);
            $video = Post::changeStatus($video, $status);
            $video->reason = $reason;
            $video->save();
            Post::processSaveLog([], $video, 2, []);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function changeStatus(string $id, Request $request)
    {
        try {
            $video = Post::findOrFail($id);
            $status = $request->get('status');
            $video = Post::changeStatus($video, $status);
            $video->save();
            Post::processSaveLog([], $video, 2, []);
            $request->replace(['id' => $id]);
            return $this->index($request)->getData();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function massChangeStatus(Request $request)
    {
        try {
            DB::beginTransaction();
            $id_array = json_decode($request->get('multi-id'), true);
            $status = $request->get('multi-status');
            foreach ($id_array as $video_id) {
                $video = Post::findOrFail($video_id);
                if ($status == 3) {
                    if ($video->status == 1) {
                        $status = 2;
                    }
                }
                $video = Post::changeStatus($video, $status);
                $video->save();
                Post::processSaveLog([], $video, 2, []);
            }
            DB::commit();
            return back()->with('success', $this->title . '状态编辑成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
