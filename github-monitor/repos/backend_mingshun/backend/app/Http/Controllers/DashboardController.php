<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Role;
use App\Models\TokenLogs;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoChoose;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard(){
        $value = [];
        $currentYear = Carbon::now()->year;
        if(!Auth::user()->checkUserRole([1,2])){
            $header = ['日期','数量'];
            $title = '日常统计';
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime("-1 days"));
            $user_id = Auth::user()->id;
            if(Auth::user()->isUploader()){
                $video = Video::select(DB::raw('DATE(videos.created_at) as date'), DB::raw('COUNT(*) as count'))
                    ->where('uploader', $user_id)
                    ->where('videos.created_at','>',$yesterday . " 00:00:00")
                    ->groupBy('date')->get()->pluck('count','date')->toArray();
                $total = User::withCount(['videos' => function($query) use ($user_id){
                    $query->where('videos.uploader',$user_id);
                }])->whereHas('videos', function ($query) use ($user_id){
                    $query->where('videos.uploader',$user_id);
                })->first();
                $value[]=[
                    'type' => 'table',
                    'header' => $header,
                    'title' =>  Role::find(3)->name .  $title,
                    'value' => [
                        '今天' => $video[$today] ?? 0, 
                        '昨天' => $video[$yesterday] ?? 0, 
                        '<b>总数</b>' => '<b>' .($total?$total->videos_count:0) . '</b>',
                    ],
                ];
                
                if( Auth::user()->projects->pluck('id')->toArray()){
                    if (in_array(Project::MINGSHUN, Auth::user()->projects->pluck('id')->toArray())){   
                        $currentMonth = Carbon::now()->format('m'); 
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                                ->whereMonth('created_at',$currentMonth)
                                ->whereYear('created_at', $currentYear)
                                ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '这个月徽章数',
                            'value' => $temp,
                        ];
    
                        $lastMonth = Carbon::now()->subMonth()->format('m');
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                            ->whereMonth('created_at',$lastMonth)
                            ->whereYear('created_at', $currentYear)
                            ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '上个月徽章数',
                            'value' => $temp,
                        ];

                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '徽章总数',
                            'value' => $temp,
                        ];
                    }
                }
                $totalVideoBeingAssigned = Video::where('uploader',$user_id)->count();
                $totalSuccessVideo = Video::where('uploader',$user_id)->where('status', 3)->count();
                $successRate = '-';
                if($totalVideoBeingAssigned){
                    $successRate = number_format((float)($totalSuccessVideo / $totalVideoBeingAssigned) * 100, 2, '.', '')  . "%";
                }
                $value[]=[
                    'type' => 'text',
                    'title' => '上传成功率',
                    'value' => $successRate,
                ];
            }
            if(Auth::user()->isReviewer()){
                $video = Video::select(DB::raw('DATE(videos.first_approved_at) as date'), DB::raw('COUNT(*) as count'))
                    ->where('first_approved_by', $user_id)
                    ->where('videos.first_approved_at','>',$yesterday . " 00:00:00")
                    ->groupBy('date')->get()->pluck('count','date')->toArray();
                $total = User::withCount(['firstApprove' => function($query) use ($user_id){
                    $query->where('videos.first_approved_by',$user_id);
                }])->whereHas('firstApprove', function ($query) use ($user_id){
                    $query->where('videos.first_approved_by',$user_id);
                })->first();
                $value[]=[
                    'type' => 'table',
                    'header' => $header,
                    'title' =>  Role::find(4)->name .  $title,
                    'value' => [
                        '今天' => $video[$today] ?? 0, 
                        '昨天' => $video[$yesterday] ?? 0, 
                        '<b>总数</b>' => '<b>' .($total?$total->first_approve_count:0) . '</b>',
                    ],
                ];
                if( Auth::user()->projects->pluck('id')->toArray()){
                    if (in_array(Project::MINGSHUN, Auth::user()->projects->pluck('id')->toArray())){   
                        $currentMonth = Carbon::now()->format('m'); 
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                            ->whereMonth('created_at',$currentMonth)
                            ->whereYear('created_at', $currentYear)
                            ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '这个月徽章数',
                            'value' => $temp,
                        ];
    
                        $lastMonth = Carbon::now()->subMonth()->format('m');
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                            ->whereMonth('created_at',$lastMonth)
                            ->whereYear('created_at', $currentYear)
                            ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '上个月徽章数',
                            'value' => $temp,
                        ];

                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '徽章总数',
                            'value' => $temp,
                        ];
                    }
                }
                $totalVideoBeingAssigned = Video::where('assigned_to',$user_id)->count();
                $totalSuccessVideo = Video::where('assigned_to',$user_id)->where('status', 3)->count();
                $successRate = '-';
                if($totalVideoBeingAssigned){
                    $successRate = number_format((float)($totalSuccessVideo / $totalVideoBeingAssigned) * 100, 2, '.', '')  . "%";
                }
                $value[]=[
                    'type' => 'text',
                    'title' => '审核成功率',
                    'value' => $successRate,
                ];
                $value[]=[
                    'type' => 'text',
                    'title' => '被提交重新审核次数',
                    'value' => Video::where('assigned_to',$user_id)->whereNotNull('rereviewer_by')->count(),
                ];
                $value[]=[
                    'type' => 'text',
                    'title' => '每日任务',
                    'value' => USER::PRESS[Auth::user()->is_daily_press],
                ];
                $value[]=[
                    'type' => 'text',
                    'title' => '额外任务',
                    'value' => USER::PRESS[Auth::user()->is_extra_press],
                ];
            }elseif(Auth::user()->isCoverer()){
                $video = Video::select(DB::raw('DATE(videos.cover_changed_at) as date'), DB::raw('COUNT(*) as count'))
                    ->where('cover_assigned_to', $user_id)
                    ->where('videos.cover_changed_at','>',$yesterday . " 00:00:00")
                    ->groupBy('date')->get()->pluck('count','date')->toArray();
                $total = User::withCount(['coverAssigned' => function($query) use ($user_id){
                    $query->where('videos.cover_assigned_to',$user_id);
                }])->whereHas('coverAssigned', function ($query) use ($user_id){
                    $query->where('videos.cover_assigned_to',$user_id);
                })->first();
                $value[]=[
                    'type' => 'table',
                    'header' => $header,
                    'title' =>  Role::find(7)->name .  $title,
                    'value' => [
                        '今天' => $video[$today] ?? 0, 
                        '昨天' => $video[$yesterday] ?? 0, 
                        '<b>总数</b>' => '<b>' .($total?$total->cover_assigned_count:0) . '</b>',
                    ],
                ];
                if( Auth::user()->projects->pluck('id')->toArray()){
                    if (in_array(Project::MINGSHUN, Auth::user()->projects->pluck('id')->toArray())){   
                        $currentMonth = Carbon::now()->format('m'); 
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                            ->whereMonth('created_at',$currentMonth)
                            ->whereYear('created_at', $currentYear)
                            ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '这个月徽章数',
                            'value' => $temp,
                        ];

                        $lastMonth = Carbon::now()->subMonth()->format('m');
                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)
                            ->whereMonth('created_at',$lastMonth)
                            ->whereYear('created_at', $currentYear)
                            ->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '上个月徽章数',
                            'value' => $temp,
                        ];

                        $temp = [];
                        foreach(TokenLogs::TYPE as $key=>$tokenLog){
                            $temp[$tokenLog] = TokenLogs::where('user_id',$user_id)->where('type',$key)->count();
                        }
                        $value[]=[
                            'type' => 'table',
                            'header' => $header,
                            'title' =>  '徽章总数',
                            'value' => $temp,
                        ];
                    }
                }
                $value[]=[
                    'type' => 'text',
                    'title' => '每日任务',
                    'value' => USER::PRESS[Auth::user()->is_daily_press],
                ];
                $value[]=[
                    'type' => 'text',
                    'title' => '额外任务',
                    'value' => USER::PRESS[Auth::user()->is_extra_press],
                ];
            }elseif(Auth::user()->isProjectSupervisor()){
                $projects = Auth::user()->projects->first();
                $videoChooses = DB::table('video_chooses')
                    ->select('status', DB::raw('count(*) as total'))
                    ->where('project_id',$projects->id)
                    ->whereMonth('created_at', date('m'))
                    ->whereYear('created_at', date('Y'))
                    ->groupBy('status')
                    ->pluck('total','status')
                    ->toArray();
                $tableValue = [];
                foreach($videoChooses as $id => $count){
                    $statusNumber = $id;
                    if($statusNumber == 9 || $statusNumber == 11){
                        $statusNumber = 2;
                    }else if($statusNumber == 10 || $statusNumber == 12 || $statusNumber == 13){
                        $statusNumber = 3;
                    }
                    $tableValue[VideoChoose::STATUS[$statusNumber]] = $count;
                }
                $value[]=[
                    'type' => 'table',
                    'header' => ['状态','总计'],
                    'title' =>  '本月视频总计('.date('m').'月)',
                    'value' => $tableValue,
                ];
            }
        }
       
        return view('dashboard',[
            "values"=> $value
        ]);
    }
}
