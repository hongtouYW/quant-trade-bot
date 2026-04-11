<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoProjectRule;
use App\Models\Server;
use App\Models\User;
use App\Models\Video;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PhotoApiController extends Controller
{
    public function photo(Request $request)
    {
        try{
            $photo = $request->all();
            if(!isset($photo['title'])){
                throw new Exception("缺少title参数");
            }
            if(!($photo['rule'] ?? '')){
                throw new Exception("直切缺少rule参数");
            }
            if(!($photo['username'] ?? '')){
                throw new Exception("直切缺少username参数");
            }
            if(!($photo['image'] ?? '')){
                throw new Exception("直切缺少image参数");
            }
            $photoInDb = Photo::where('path', $photo['image'])->first();
            if($photoInDb){
                throw new Exception("image链接重复");
            }
            $user = User::where('username', $photo['username'])->first();
            if(!$user){
                throw new Exception("用户名字不正确");
            }
            $uploader = $user->id;
            $projects = $user->projects->first();

            $rules = PhotoProjectRule::where('name', $photo['rule'])->where('project_id',$projects->id)->first();
            if(!$rules){
                throw new Exception("rule错误/不存在");
            }
            $ruleId = $rules->id;

            $header = Video::checkFileHeader('https://resources.minggogogo.com' . "/public" . $photo['image']);
            $lan_ip = explode(":",$header)[0];
            $server = Server::where('lan_domain','like', '%'.$lan_ip . '%')->first();
            if(!$server){
                throw new \Exception('资源损坏。');
            }
            $server_id = $server->id;

            if($photo['image'] ?? ''){
                $response = Video::checkValid($photo['image'], 1);
                if (($response->code ?? 0) != 200) {
                    if($response->errorMessage ?? ''){
                        if(gettype($response->errorMessage) == 'string'){
                            $error = $response->errorMessage;
                        }else{
                            $error = json_encode($response->errorMessage);
                        }   
                    }else{
                        if(gettype($response) == 'string'){
                            $error = $response;
                        }else{
                            $error = json_encode($response);
                        }
                    }
                    throw new Exception('图片' . $error);
                }else{
                    if (isset($response->data->path)) {
                        $photo['image'] = $response->data->path;
                    }
                }
            }

            $photoDB = Photo::create([
                'title' => $photo['title'],
                'path' => $photo['image'],
                'uploader' => $uploader,
                'server_id' => $server_id,
                'project_id' => $projects->id,
                'photo_project_rule_id' => $ruleId,
                'status' => 1
            ]);
            if($photo['output_dir'] ?? ''){
                $folderName = '/' .$photo['output_dir'];
            }else{
                $folderName = '';
            }
            $id = $photoDB->id;
            $savePath = $projects->name . $folderName .  "/".time() . '_' ."imageWatermark" . $id. ".png";
            $photoPath = $server->play_domain . "/public" . $photo['image'];
            list($response,$coverNewPath) = Photo::addWatermarkImage($photoPath,$ruleId,$savePath ,$projects->id);
            if (!$response) {
                DB::rollBack();
                return response()->json([
                    'message' => '图片'.$coverNewPath,
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $photoDB->status = 3;
            $photoDB->save_path = $savePath;
            $photoDB->save();
            DB::commit();
            return response()->json([
                'code' => 1,
                'data' => [
                    'photo_id' => $id,
                    'path' => $savePath
                ],
                'message' => '成功',
            ], Response::HTTP_OK);
        }catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Photo Api--' . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
