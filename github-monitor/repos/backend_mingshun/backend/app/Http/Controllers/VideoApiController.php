<?php

namespace App\Http\Controllers;

use App\Http\Helper;
use App\Models\Author;
use App\Models\Config;
use App\Models\CutServer;
use App\Models\Ftp;
use App\Models\Project;
use App\Models\ProjectRules;
use App\Models\Server;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class VideoApiController extends Controller
{
    public function video(Request $request)
    {
        try {
            $request->validate(['videos' => 'required|array']);
            $videos = $request->get('videos');

            $randomString = bin2hex(random_bytes(4));
            $processId = getmypid();

            DB::beginTransaction();
            foreach ($videos as $key => $video) {
                if (!isset($video['code_others'])) {
                    $video['code_others'] = '';
                } else {
                    $videoTemp = Video::where('code_others', $video['code_others'])->first();
                    if ($videoTemp) {
                        return response()->json([
                            'message' => "定制番号已重复",
                            'code' => "1",
                            'path' => $video['path'] ?? ''
                        ], 202);
                    }
                }

                if (!isset($video['md5'])) {
                    $video['md5'] = '';
                }

                $server_id = Video::baseCheckLanDomain('https://resources.minggogogo.com',$video['path'] ?? '',$video['cover_photo'] ?? '',$video['cover_vertical'] ?? '');
                if (!isset($video['others'])) {
                    $video['others'] = '';
                }
                $type_id = 0;
                if (isset($video['type'])) {
                    $type_id = Type::firstOrCreate([
                        'name' => trim($video['type']),
                    ], [
                        'status' => 1,
                    ]);
                }
                $author = 0;
                if (isset($video['author'])) {
                    $author = Author::firstOrCreate([
                        'name' => $video['author'],
                    ], [
                        'status' => 1,
                    ]);
                }
                if ($author) {
                    $video['author_id'] = $author->id;
                }
                $video['server_id'] = $server_id;
                $video['uploader'] = 0;
                $video['status'] = 1;
                $video['uid'] = uniqid($randomString . $processId);
                $video['created_at'] = now();
                $video['resolution_tag'] = Video::getResolutionTag($video['resolution'] ?? "");
                unset($video['server'], $video['type']);

                $videoInsert = Video::create($video);
                $videoArray = [];
                if ($type_id) {
                    $videoArray = ['types' => strval($type_id->id ?? '')];
                    $videoInsert->types()->sync($type_id);
                }
                Video::processSaveLog($videoArray, $videoInsert, 1);
            }

            DB::commit();
            return response()->json([
                'message' => '成功创建',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Api--' . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoImport2(Request $request)
    {
        try {
            DB::beginTransaction();
            Video::videoApi($request, [5], [10]);
            DB::commit();
            return response()->json([
                'code' => 1,
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Import--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoImport(Request $request)
    {
        try {
            DB::beginTransaction();
            Video::videoApi($request, [Video::SHOWCODEOTHERS], []);
            DB::commit();
            return response()->json([
                'code' => 1,
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Import2--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoCustomImportDirect(Request $request)
    {
        try {
            DB::beginTransaction();
            $video = Video::customImportBase($request, 0);
            $id = $video->id;
            $video->first_approved_by = 1;
            $video->first_approved_at = now();
            $video->status = 3;
            $video->save();
            DB::commit();
            return response()->json([
                'code' => 1,
                'data' => [
                    'video_id' => $id,
                ],
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Custom Import Direct--' . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoCustomImport(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = Video::customImportBase($request);
            DB::commit();
            return response()->json([
                'code' => 1,
                'data' => [
                    'video_id' => $id,
                ],
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Custom Import--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoStatistics(Request $request)
    {
        if(!$request->token){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '缺少参数',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $project = Project::where('token', $request->token)->first();
        if(!$project){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => 'token错误',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if ($request->date) {
            if (Carbon::hasFormat($request->date, 'Y-m-d')) {
                $date = $request->date;
            } else {
                return response()->json([
                    'code' => 0,
                    'data' => [],
                    'message' => '日期格式错误，必须是YYYY-MM-DD',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } else {
            $date = now()->format('Y-m-d');
        }
        $result = VideoChoose::query()
            ->selectRaw("
                SUM(CASE WHEN callback_at IS NOT NULL 
                        AND DATE(callback_at) = ? 
                        AND status IN (4,5,7) THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN cut_at IS NOT NULL 
                        AND DATE(cut_at) = ? 
                        AND status IN (2,9,11) THEN 1 ELSE 0 END) AS cutting_count,
                SUM(CASE WHEN cut_at IS NOT NULL 
                        AND DATE(cut_at) = ? 
                        AND status IN (3,10,12,13) THEN 1 ELSE 0 END) AS failed_count
            ", [$date, $date, $date])
            ->where('project_id', $project->id)
            ->first();

        return response()->json([
            'code' => 1,
            'data' => [
                'project' => $project->name,
                'date' => $date,
                'data' => [
                    '成功' => $result->success_count,
                    '切片中' => $result->cutting_count,
                    '失败' => $result->failed_count,
                ],
            ],
            'message' => '成功',
        ], Response::HTTP_OK);
    }

    public function videoReviewInfo(Request $request)
    {
        if(!$request->token || !$request->video_id){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '缺少参数',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $project = Project::where('token', $request->token)->first();
        if(!$project){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => 'token/视频id错误',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $video = Video::find($request->video_id);
        if(!$video){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => 'token/视频id错误',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $user = User::find($video->uploader);
        if(!$user){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '没权限',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $user_project_id = $user->projects->pluck('id')->toArray();
        if(!in_array($project->id,$user_project_id)){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '没权限',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        // 1= pending,2=success,3=failed
        $reason = '';
        if($video->status == 1){
            $status = 1;
            $reason = $video->reason;
        }elseif($video->status == 4){
            $status = 3;
            $reason = $video->reason;
        }else{
            $status = 2;
        }
        return response()->json([
            'code' => 1,
            'data' => [
                'status' => $status,
                'reason' => $reason
            ],
            'message' => '成功',
        ], Response::HTTP_OK);
    }

    public function videoChooseReviewInfo(Request $request)
    {
        if(!$request->token || !$request->video_id){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '缺少参数',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $project = Project::where('token', $request->token)->first();
        if(!$project){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => 'token/视频id错误',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $video = Video::find($request->video_id);
        if(!$video){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => 'token/视频id错误',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $user = User::find($video->uploader);
        if(!$user){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '没权限',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $user_project_id = $user->projects->pluck('id')->toArray();
        if(!in_array($project->id,$user_project_id)){
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '没权限',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        // 1= pending,2=success,3=failed
        $videoChoose = VideoChoose::where('video_id',$request->video_id)->whereIn('project_id',$user_project_id)
            ->orderby('id','desc')->first();
        if(!$videoChoose){
            $status = 3;
            $reason = '无此视频';
        }else{
            if($videoChoose->status == 7){
                $status = 2;
                $reason = json_encode(VideoChoose::getCallbackMessage($videoChoose));
            }elseif($videoChoose->status == 3){
                $status = 3;
                $reason = Str::limit($videoChoose->cut_callback_msg, 200, '...');
            }else{
                $status = 1;
                $reason = '';
            }
        }
        return response()->json([
            'code' => 1,
            'data' => [
                'status' => $status,
                'reason' => $reason
            ],
            'message' => '成功',
        ], Response::HTTP_OK);
    }

    public function videoCallback(Request $request)
    {
        try {
            $move=false;
            $recutTimes = Config::getCachedConfig('recut_times') ?? 1;
            if($request->input('server')){
                $ip = $request->input('server');
            }else{
                $ip = Helper::getIp();
            }
            DB::beginTransaction();
            $identifier = $request->identifier;
            $id = explode("__",$identifier);
            $videoChoose = VideoChoose::find($id[1] ?? 0);
            if(!$videoChoose){
                return response()->json([
                    'message' => '预选视频不存在',
                ], Response::HTTP_OK);
            }
            if($videoChoose->status == 6){
                return response()->json([
                    'message' => '视频已被下架',
                ], Response::HTTP_OK);
            }
            if($request->status == 7){
                return response()->json([
                    'message' => '视频已被下架',
                ], Response::HTTP_OK);
            }
            if($request->input('server') ?? ''){
                $videoChoose->server = $request->input('server');
            }
            if($request->status == 1 || $request->status == 2 || $request->status == 3){
                $project = Project::findOrFail($videoChoose->project_id);
                $project_server = $project->servers->first();
                $ps_path = $project_server->path;
                if ($request->msg) {
                    if (Helper::isValidJson($request->msg)) {
                        $msg = json_decode($request->msg);
                        unset($msg->server);
                        if($msg->play_url ?? ''){
                            $msg->play_url = $ps_path . "/" . $msg->play_url;
                        }
                        if($msg->download_url ?? ''){
                            $msg->download_url = $ps_path . "/" . $msg->download_url;
                        }
                        if($msg->thumbnail ?? ''){
                            $msg->thumbnail = $ps_path . "/" . $msg->thumbnail;
                        }
                        if($msg->thumb_longview ?? ''){
                            $msg->thumb_longview = $ps_path . "/" . $msg->thumb_longview;
                        }
                        if($msg->thumb_ver ?? ''){
                            $msg->thumb_ver = $ps_path . "/" . $msg->thumb_ver;
                        }
                        if($msg->thumb_hor ?? ''){
                            $msg->thumb_hor = $ps_path . "/" . $msg->thumb_hor;
                        }
                        if($msg->cover ?? ''){
                            $msg->cover = $ps_path . "/" . $msg->cover;
                        }
                        if($msg->webp ?? ''){
                            $msg->webp = $ps_path . "/" . $msg->webp;
                        }
                        if($msg->preview ?? ''){
                            $msg->preview = $ps_path . "/" . $msg->preview;
                        }
                        $cut_callback_success_msg = json_encode($msg). "--" . $ip;
                        $videoChoose->cut_callback_success_msg = $cut_callback_success_msg;
                        $videoChoose->cut_callback_msg = json_encode($msg). "--" . $ip;
                    }else{
                        $videoChoose->cut_callback_msg = $request->msg. "--" . $ip;
                    }
                    $videoChoose->cut_at = now();
                }

                $video = $videoChoose->video;
                if($video?->status == 5){
                    $uploaderUser = User::with('projects')->find($video->uploader);
                    if(($uploaderUser?->projects->first()?->solo ?? 0) == 1){
                        $move = false;
                    }else{
                        $move = true;
                    }
                }
            }
            
            switch ($request->status) {
                case 1:
                    $videoChoose->status = 4;
                    break;
                case 2:
                    $videoChoose->status = 8;
                    break;
                case 3:
                    $videoChoose->ai_at = now();
                    $videoChoose->status = 9;
                    break;
                case 4:
                    $videoChoose->ai_at = now();
                    $videoChoose->status = 13;
                    $videoChoose->subtitle_callback_msg = mb_substr($request->msg, 0, 2000);
                    VideoChoose::sendErrorMessageToTelegram($videoChoose->subtitle_callback_msg, $videoChoose->id,$videoChoose->project, '字幕错误', $videoChoose->video_id);
                    break;
                case 5:
                    $videoChoose->ai_at = now();
                    $videoChoose->status = 10;
                    $videoChoose->subtitle_callback_msg = mb_substr($request->msg, 0, 2000);
                    VideoChoose::sendErrorMessageToTelegram($videoChoose->subtitle_callback_msg, $videoChoose->id,$videoChoose->project, '字幕错误', $videoChoose->video_id);
                    break;
                case 6:
                    $msg = json_decode($request->msg);
                    if($msg->status == 2){
                        $videoChoose->status = 11;
                        $subtitle_callback_msg = '重新生成字幕失败';
                        if($msg?->msg){
                            $subtitle_callback_msg = $msg?->msg;
                        }else{
                            $subtitle_callback_msg = $msg;
                        }
                        $videoChoose->subtitle_callback_msg = mb_substr($subtitle_callback_msg, 0, 2000);
                    }else if($msg->status != 1){
                        $videoChoose->status = 10;
                        $subtitle_callback_msg = '生成字幕失败';
                        if($msg?->msg){
                            $subtitle_callback_msg = $msg?->msg;
                        }else{
                            $subtitle_callback_msg = $msg;
                        }
                        $videoChoose->subtitle_callback_msg = mb_substr($subtitle_callback_msg, 0, 2000);
                    }
                    VideoChoose::sendErrorMessageToTelegram($videoChoose->subtitle_callback_msg, $videoChoose->id,$videoChoose->project, '字幕错误', $videoChoose->video_id);
                    break;
                case 7:
                    $videoChoose->status = 12;
                    $sync_callback_msg = '字幕同步失败';
                    if($request->msg){
                        $sync_callback_msg = $request->msg;
                    }
                    $videoChoose->sync_callback_msg = mb_substr($sync_callback_msg, 0, 2000);
                    VideoChoose::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '字幕错误', $videoChoose->video_id);
                    break;
                case 8:
                    if(!$request->msg){
                        $videoChoose->subtitle_callback_msg = '字幕返回不能为空';
                        VideoChoose::sendErrorMessageToTelegram($videoChoose->subtitle_callback_msg, $videoChoose->id,$videoChoose->project, '字幕错误', $videoChoose->video_id);
                    }else{
                        $msg = json_decode($request->msg);
                        $language_files = $msg->language_files;
                        if (is_array($language_files)) {
                            $language_files = json_encode($language_files);
                        }
                        $videoChoose->subtitle_callback_msg = $language_files;
                    }
                    break;
                default:
                    if($videoChoose->recut_time >= $recutTimes){
                        $videoChoose->cut_callback_msg = '资源损坏';
                    }elseif($request->msg) {
                        $msg = json_decode($request->msg);
                        if($msg){
                            if(gettype($msg) == 'string'){
                                $temp = $msg;
                            }else{
                                $temp = json_encode($msg);
                            }
                        }else{
                            $temp = $request->msg;
                        }
                        $videoChoose->cut_callback_msg =  mb_substr($temp, 0, 2000);
                    }else{
                        $videoChoose->cut_callback_msg = '切片错误';
                    }
                    $videoChoose->cut_at = now();
                    $videoChoose->cut_callback_msg = $videoChoose->cut_callback_msg . "--" . $ip;
                    $videoChoose->status = 3;
                    VideoChoose::sendErrorMessageToTelegram($videoChoose->cut_callback_msg, $videoChoose->id,$videoChoose->project, '切片错误', $videoChoose->video_id);
                    $move = false;
                    break;
            }
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            DB::commit();
           
        } catch (\Exception $e) {
            DB::rollBack();
            if($videoChoose ?? ''){  
                if($videoChoose->recut_time >= $recutTimes){
                    $videoChoose->cut_callback_msg = '资源损坏';
                }else{
                    $videoChoose->cut_callback_msg = $e->getMessage() . "--" . $ip;
                }
                $videoChoose->cut_at = now();
                VideoChoose::sendErrorMessageToTelegram($videoChoose->cut_callback_msg, $videoChoose->id,$videoChoose->project, '切片错误', $videoChoose->video_id);
                $videoChoose->status = 3;
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }
            Log::channel('api_error')->info('Video Cut Callback--' . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        try{
            if ($request->status == 1) {
                $videoChoose = VideoChoose::syncVideoToCustomer($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }else if ($request->status == 2 || $request->status == 8){
                $videoChoose = VideoChoose::sendVideoCallbackToCustomer($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                if($move){
                    $response = Video::moveVideo($video->path, $video->cover_photo, $video->server_id, $video->id);
                    if (($response->code ?? 0) == 200) {
                        if (isset($response->data)) {
                            $videoPath = '';
                            if (isset($response->data->video)) {
                                $videoPath = $response->data->video;
                            }
                            $coverPath = '';
                            if (isset($response->data->image)) {
                                $coverPath = $response->data->image;
                            }
                        }
                        $video->path = $videoPath;
                        $video->cover_photo = $coverPath;
                        $video->save();
                        Video::processSaveLog([], $video, 2, []);

                        $newVideo = $video->replicate();
                        $randomString = bin2hex(random_bytes(4));
                        $processId = getmypid();
                        $newVideo->uid = uniqid($randomString . $processId);
                        $newVideo->status = 1;
                        $newVideo->uploader = 0;
                        $newVideo->save();
                        Video::processSaveLog([], $newVideo, 1);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Video Sync--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            if($videoChoose){
                $videoChoose->sync_callback_msg = $e->getMessage();
                VideoChoose::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
                $videoChoose->status = 4;
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }
            
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => '成功',
        ], Response::HTTP_OK);
    }

    public function videoSyncCallback(Request $request)
    {
        try {
            DB::beginTransaction();
            $identifier = $request->identifier;
            $id = explode("__",$identifier);
            $videoChoose = VideoChoose::find($id[1] ?? 0);
            if(!$videoChoose){
                return response()->json([
                    'message' => '预选视频不存在',
                ], Response::HTTP_OK);
            }
            if($videoChoose->status == 5){
                return response()->json([
                    'message' => '视频不是同步中状态',
                ], Response::HTTP_OK);
            }
            if($videoChoose->status == 6){
                return response()->json([
                    'message' => '视频已被下架',
                ], Response::HTTP_OK);
            }
            if ($request->msg) {
                $videoChoose->sync_callback_msg = $request->msg;
                $msg = json_decode($request->msg);
                if($msg){
                    if(gettype($msg) == 'string'){
                        $temp = $msg;
                    }else{
                        $temp = json_encode($msg);
                    }
                    $videoChoose->sync_callback_msg = $temp;
                }else{
                    $videoChoose->sync_callback_msg = $request->msg;
                }
            }
            if($request->status){
                $videoChoose->status = 8;
            }else{
                VideoChoose::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
                $videoChoose->status = 4;
            }
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if($videoChoose ?? ''){
                $videoChoose->sync_callback_msg = $e->getMessage();
                VideoChoose::sendErrorMessageToTelegram($videoChoose->sync_callback_msg, $videoChoose->id,$videoChoose->project, '同步错误', $videoChoose->video_id);
                $videoChoose->status = 4;
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }
            Log::channel('api_error')->info('Video Sync Callback--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        try{
            if ($request->status) {
                $videoChoose = VideoChoose::sendVideoCallbackToCustomer($videoChoose);
            } 
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Video Send--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            if($videoChoose){
                $videoChoose->send_callback_msg = $e->getMessage();
                VideoChoose::sendErrorMessageToTelegram($videoChoose->send_callback_msg, $videoChoose->id,$videoChoose->project, '发送错误', $videoChoose->video_id);
                $videoChoose->status = 8;
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response()->json([
            'message' => '成功',
        ], Response::HTTP_OK);
    }

    public function updateMsgCallback(Request $request){
        try {
            DB::beginTransaction();
            $identifier = $request->identifier;
            $id = explode("__",$identifier);
            $videoChoose = VideoChoose::find($id[1] ?? 0);
            if(!$videoChoose){
                return response()->json([
                    'message' => '预选视频不存在',
                ], Response::HTTP_OK);
            }
            if($videoChoose->status !== 2){
                return response()->json([
                    'message' => '视频不是切片中状态',
                ], Response::HTTP_OK);
            }
            $videoChoose->cut_at = now();
            $msg = $request->msg;
            if ($msg) {
                $cut_callback_msg = explode("--", $videoChoose->cut_callback_msg);
                $cut_callback_msg = ($cut_callback_msg[0] ?? '');
                if($request->input('server')){
                    $ip = $request->input('server');
                }else{
                    $ip = Helper::getIp();
                }
                $msg = $msg . "--" . $ip;
                $videoChoose->cut_callback_msg = $msg;
            }
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            DB::commit();

            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Video Update Message Callback--' . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function testCallback(Request $request){
        try {
            return response()->json([
                'code' => 200,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Test Callback--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function recut(Request $request){
        try {
            $identifier = $request->identifier;
            if($identifier){
                $id = explode("__",$identifier);
                $id = $id[1] ?? 0;
            }else{
                $id = $request->id;
            }
            $videoChoose = VideoChoose::findOrFail($id);
            $temp = new \stdClass();
            $temp->theme = $videoChoose->types?->pluck('id')->toArray();
            $temp->rule = $videoChoose->project_rule_id;
            $videoChoose = VideoChoose::cutStatus($temp, $videoChoose->id, 1, 0);

            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Recut Callback--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetCut(Request $request){
        try {
            $identifier = $request->identifier;
            if($identifier){
                $id = explode("__",$identifier);
                $id = $id[1] ?? 0;
            }else{
                $id = $request->id;
            }
            $videoChoose = VideoChoose::findOrFail($id);
            if($videoChoose->status != 2 && $videoChoose->status != 3){
                return response()->json([
                    'message' => "预选状态不是切片中/切片失败",
                    'code' => "0",
                ], 202);
            }
            Video::checkLanDomain($videoChoose->video);
            $videoChoose->status = 1;
            $videoChoose->cut_at = null;
            $videoChoose->cut_callback_msg = '';
            $videoChoose->save();
            VideoChoose::processSaveLog([], $videoChoose, 2, []);
            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Reset Cut Callback--'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function down(Request $request){
        try {
            DB::beginTransaction();
            $ids = $request->id;
            foreach($ids as $id){
                $video = video::findOrFail($id);
                video::changeStatus($video, 4);
            }
            DB::commit();
            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('api_error')->info('Down Video --'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSubtitle(Request $request){
        try {
            $request->validate([
                'status' => 'required|integer',
                'msg' => 'required|string',
                'file' => 'file', 
                'id' => 'required|string',
                'language_files' => '',
            ]);
            $id = explode("__",$request->id);
            $id = $id[1] ?? 0;
            $videoChoose = VideoChoose::findOrFail($id);
            $videoChoose->ai_at = now();
            if($request->status == 2){
                $videoChoose->status = 11;
                $videoChoose->subtitle_callback_msg = $request->msg ?? 'Regenerate Subtitle';
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'message' => '成功',
                ], Response::HTTP_OK);
            }else if($request->status != 1){
                $videoChoose->status = 10;
                $videoChoose->subtitle_callback_msg = $request->msg ?? 'Generate Subtitle Error';
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'message' => '成功',
                ], Response::HTTP_OK);
            }else if($request->status == 1){
                if (!$request->hasFile('file')) {
                    return response()->json([
                        'message' => '状态成功没文件',
                    ], Response::HTTP_OK);
                }
            }

            $checkFail = true;
            if($videoChoose->cut_callback_success_msg){
                $checkFail = false;
            }elseif($videoChoose->cut_callback_msg){
                $cut_callback_msg = explode("--", $videoChoose->cut_callback_msg);
                $cut_callback_msg = ($cut_callback_msg[0] ?? '');
                if (Helper::isValidJson($cut_callback_msg)) {
                    $checkFail = false;
                }
            }
            if($checkFail){
                $videoChoose->status = 3;
                $videoChoose->subtitle_callback_msg = '视频资料有问题清重新切片';
                $videoChoose->cut_callback_msg = '视频资料有问题清重新切片';
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'message' => '视频资料有问题清重新切片',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $language_files = $request->language_files;
            if (is_array($language_files)) {
                $language_files = json_encode($language_files);
            }
            $videoChoose->subtitle_callback_msg = $language_files;

            if($videoChoose->status == 7){
                return response()->json([
                    'message' => '已成功',
                ], Response::HTTP_OK);
            }elseif(in_array($videoChoose->status, [1,3,4,5,6])){
                return response()->json([
                    'message' => '视频状态错误',
                ], Response::HTTP_OK);
            }
            $file = $request->file('file');
            $filename = 'subtitlefile_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = 'storage/app/public/temp/' . $filename;
            $file->move(public_path('storage/temp'), $filename);

            $project = Project::findOrFail($videoChoose->project_id);
            $project_server = $project->servers->first();
            if(!$project_server){
                $videoChoose->status = 10;
                $videoChoose->sync_callback_msg = 'No Project Server Set';
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'message' => '项目设置错误',
                ], Response::HTTP_OK);
            }
            $data = [
                'videoId' => $request->id,
                'compressFile' => $path,
                'receiver' => [
                    'username' => $project_server->username,
                    'host' => $project_server->ip,
                    'port' => (int)$project_server->port,
                    'identifier' => $videoChoose->video?->uid . "__" . $videoChoose->id,
                    'path' => $project_server->absolute_path,
                ]
            ];
            $response = Helper::sendResourceRequest(
                'http://localhost:9999/subtitle/send',
                json_encode($data),
                array('Content-Type: application/json'),
                'Sync Video Subtitle'
            );
            $response_decode = json_decode($response);
            if ((($response_decode->code ?? 0) != 200) || (($response_decode->msg ?? '') != 'success')) {
                $videoChoose->status = 12;
                if($response_decode->msg ?? ''){
                    $videoChoose->sync_callback_msg = $response_decode->msg;
                }else{
                    $videoChoose->sync_callback_msg = 'Subtitle Sync Error';
                }
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'message' => '字幕同步错误',
                ], Response::HTTP_OK);
            }else{
                $videoChoose = VideoChoose::sendVideoCallbackToCustomer($videoChoose);
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
            }
            return response()->json([
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Get Subtitle --'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function videoServerInfo(Request $request){
        try{
            $request->validate([
                'id' => 'required',
            ]);
            $id = explode("__",$request->id);
            $id = $id[1] ?? 0;
            $videoChoose = VideoChoose::findOrFail($id);
            $project = Project::findOrFail($videoChoose->project_id);
            $project_server = $project->servers->first();
            if(!$project_server){
                $videoChoose->status = 10;
                $videoChoose->sync_callback_msg = 'No Project Server Set';
                $videoChoose->save();
                VideoChoose::processSaveLog([], $videoChoose, 2, []);
                return response()->json([
                    'code' => 0,
                    'message' => '项目设置错误',
                ], Response::HTTP_OK);
            } 
            return response()->json([
                'code' => 1,
                'data' => [
                    'username' => $project_server->username,
                    'host' => $project_server->ip,
                    'port' => (int)$project_server->port,
                    'path' => $project_server->absolute_path,
                ],
                'message' => '成功',
            ], Response::HTTP_OK);
        }catch (\Exception $e) {
            Log::channel('api_error')->info('Get Video Server Info --'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
    }

    public function ftp(){
        $ftps = Ftp::select('nickname', 'password')->get();

        $formatted = $ftps->map(function ($ftp) {
            return "{$ftp->nickname}\n{$ftp->password}";
        })->implode("\n");

        return $formatted;
    }

    public function getVideoChooseRule(Request $request){
        try{
            $request->validate([
                'id' => 'required',
            ]);
            
            $id = $request->id;

            $videoChoose = VideoChoose::with(['video', 'project.servers'])->findOrFail($id);
            $video = $videoChoose->video;
            $rule = ProjectRules::findOrFail($videoChoose->project_rule_id);

            $videoServerId = Video::checkLanDomain($video);
            $videoServer = Server::findOrFail($videoServerId);
            $project = $videoChoose->project;
            $project_server = $project->servers->first();
            if(!$project_server){
                return response()->json([
                    'code' => 0,
                    'message' => 'No Project Server Set',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $clientIp = $request->ip();
            $cutServer = CutServer::where('ip', $clientIp)->first();

            $domain = $videoServer->domain;
            $lan = false;
            if ($cutServer && $cutServer->idc && $videoServer->idc) {
                if ($cutServer->idc === $videoServer->idc && $videoServer->lan_domain) {
                    $domain = $videoServer->lan_domain;
                    $lan = true;
                }
            } 
            $headVideo = $rule->head_video ? asset($rule->head_video): "";
            $adImage = $rule->ad_image ? asset($rule->ad_image): "";
            $logoImage = $rule->logo_image ? asset($rule->logo_image): "";
            $subtitleLink = $video->subtitle ? asset($video->subtitle): "";

            if ($lan && $videoServer->lan_domain) {
                $headVideo = str_replace("https://", "http://", $headVideo);
                $adImage = str_replace("https://", "http://", $adImage);
                $logoImage = str_replace("https://", "http://", $logoImage);
                $subtitleLink = str_replace("https://", "http://", $subtitleLink);
            }

            $data = [
                'identifier' => $video->uid . "__" . $videoChoose->id,
                'video' => $domain . '/download' . $video->path,
                'cover' => $video->cover_photo ? $domain . '/download' . $video->cover_photo : '',
                'coverVer' => $video->cover_vertical ? $domain . '/download' . $video->cover_vertical : '',
                'projectId' => $videoChoose->project_id,
                'rule' => [
                    'resolutionOption' => (int)$rule->resolution_option,
                    'webp' => [
                        'enable' => boolval($rule->webp_enable),
                        'start' => (int)$rule->webp_start,
                        'interval' => (int)$rule->webp_interval,
                        'count' => (int)$rule->webp_count,
                        'length' => (int)$rule->webp_length,
                    ],
                    'skipVideo'=> [
                        'enable' => boolval($rule->skip_enable),
                        'head' => (int)$rule->skip_head,
                        'back' => (int)$rule->skip_back,
                    ],
                    'headVideo' => [
                        'enable' => boolval($rule->head_enable),
                        'video' => $headVideo,
                    ],
                    'ad' => [
                        'enable' => boolval($rule->ad_enable),
                        'image' => $adImage,
                        'start' => (int)$rule->ad_start,
                    ],
                    'logo' => [
                        'enable' => boolval($rule->logo_enable),
                        'image' => $logoImage,
                        'position' => (int)$rule->logo_position,
                        'padding' => (int)$rule->logo_padding,
                        'scale' => (int)$rule->logo_scale,
                    ],
                    'font' => [
                        'enable' => boolval($rule->font_enable),
                        'text' => $rule->font_text,
                        'color' => $rule->font_color,
                        'size' => (int)$rule->font_size,
                        'position' => (int)$rule->font_position,
                        'interval' => (int)$rule->font_interval,
                        'scroll' => (int)$rule->font_scroll,
                        'border' => (int)$rule->font_border,
                        'shadow' => boolval($rule->font_shadow),
                        'space' => (int)$rule->font_space,
                    ],
                    'preview' => [
                        'enable' => boolval($rule->preview_enable),
                        'interval' => (int)$rule->preview_interval,
                        'skip' => (int)$rule->preview_skip,
                    ],
                    'm3u8' => [
                        'enable' => boolval($rule->m3u8_enable),
                        'encrypt' => boolval($rule->m3u8_encrypt),
                        'interval' => (int)$rule->m3u8_interval,
                        'bitrate' => (int)$rule->m3u8_bitrate,
                        'fps' => (int)$rule->m3u8_fps,
                    ],
                    'subtitle' => [
                        'enable' => boolval(($video->subtitle ? 1 : 0)),
                        'file' => $subtitleLink,
                    ],
                    'aiSubtitle' => [
                        'enable' => boolval($rule->generate_subtitle),
                        'language' => $rule->subtitle_languages ? json_decode($rule->subtitle_languages) : null,
                    ],
                ],
                'receiver' => [
                    'username' => $project_server->username,
                    'host' => $project_server->ip,
                    'port' => (int)$project_server->port,
                    'identifier' => $videoChoose->video?->uid . "__" . $videoChoose->id,
                    'path' => $project_server->absolute_path,
                ]
            ];

            return response()->json([
                'code' => 1,
                'data' => $data,
                'message' => '成功',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::channel('api_error')->info('Video API --'  . $e->getMessage(). '--' . $e->getFile() . '--' . $e->getLine());
            return response()->json([
                'code' => 0,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
