<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Post;
use App\Models\PostChoose;
use App\Models\PostImage;
use App\Models\PostVideo;
use App\Models\Server;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PostApiController extends Controller
{
    public function post(Request $request)
    {
        try {
            $posts = $request->get('posts');
            $servers = Server::active()->pluck('ip', 'id')->toArray();

            DB::beginTransaction();
            foreach ($posts as $key => $post) {
                $server_id = array_search($post['server']['ip'], $servers);
                if (!isset($post['uid'])) {
                    return response()->json([
                        'msg' => '没uid',
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }else{
                    $postCheck = Post::where('uid', $post['uid'])->first();
                    if($postCheck){
                        return response()->json([
                            'msg' => 'uid已使用',
                        ], Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
                if (!$server_id) {
                    $server = Server::create([
                        'ip' => $post['server']['ip'],
                        'domain' => $post['server']['domain'],
                        'name' => $post['server']['name'],
                        'path' => $post['server']['path'],
                        'status' => 1,
                        'created_by' => 0,
                        'created_at' => now()
                    ]);
                    $server_id = $server->id;
                } else {
                    $server = Server::findOrFail($server_id);
                    $server->update([
                        'domain' => $post['server']['domain'],
                        'name' => $post['server']['name'],
                        'path' => $post['server']['path'],
                    ]);
                }
               
                $type_id = 0;
                if (isset($post['type'])) {
                    $type_id = Type::firstOrCreate([
                        'name' => $post['type'],
                    ], [
                        'status' => 1,
                    ]);
                }

                $postInsert = Post::create([
                    'uid' => $post['uid'],
                    'title' => $post['title'],
                    'description' => $post['description'],
                    'server_id' => $server_id,
                    'uploader' => 1,
                    'status' => 1,
                    'others' => $post['others'],
                    'created_at' => now()
                ]);

                if (isset($post['images'])) {
                    foreach($post['images'] as $image){
                        PostImage::create([
                            'path' => $image,
                            'post_id' => $postInsert->id
                        ]);
                    }
                 }
 
                 if (isset($post['videos'])) {
                     foreach($post['videos'] as $video){
                         PostVideo::create([
                             'path' => $video,
                             'post_id' => $postInsert->id
                         ]);
                     }
                  }

                $postArray = [];
                if ($type_id) {
                    $postArray = ['types' => strval($type_id->id ?? '')];
                    $postInsert->types()->sync($type_id);
                }
                Post::processSaveLog($postArray, $postInsert, 1);
            }

            DB::commit();
            return response()->json([
                'message' => '成功创建',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Post Api--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // @TODO change to newest hugo
    public function postSendCallback(Request $request)
    {
        try {
            DB::beginTransaction();
            $postChoose = PostChoose::findOrFail($request->id);
            if ($request->status) {
                $postChoose->status = 4;
            } else {
                $postChoose->status = 3;
            }
            if ($request->msg) {
                if($postChoose->return_msg){
                    $postChoose->return_msg .= '<br>' . $request->msg;
                }else{
                    $postChoose->return_msg = $request->msg;
                }
            }
            $postChoose->save();

            DB::commit();
            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Post Video Callback--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}