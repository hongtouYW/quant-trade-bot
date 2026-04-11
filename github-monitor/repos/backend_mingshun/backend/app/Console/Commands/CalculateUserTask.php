<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\TokenLogs;
use App\Models\User;
use App\Models\Video;
use Carbon\Carbon;

class CalculateUserTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-user-task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate User Task';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         // 1:超级管理员，2:分类主管，3:上传手，4:审核手，5:项目运营，6:项目主管, 7:图片手
        // calculate user task and give daily token (审核手)
        $users = User::whereHas('role', function ($query) {
            $query->where('id', 4);
        })->whereHas('projects', function ($query) {
            $query->where('project_id', Project::MINGSHUN);
        })->get();
        $yesterday = Carbon::now()->subDay();
        $dayyesterday = Carbon::now()->subDay()->subDay();
        foreach($users as $user){
            $extra = 0;
            $assignedVideo = Video::where('status', 1)->where('assigned_to', $user->id)
                ->whereNull('rereviewer_by')->first();
            $failedVideo = Video::where('status', 1)->where('assigned_to', $user->id)
                ->whereNotNull('rereviewer_by')->whereDate('rereviewer_at', "<=",$dayyesterday)->first();
            if($user->is_daily_press){
                if(!$assignedVideo && !$failedVideo){
                    if($user->is_extra_press){
                        $count = Video::where('status', '!=', 1)->where('assigned_to', $user->id)
                            ->whereDate('first_approved_at', $yesterday)
                            ->whereNull('rereviewer_by')->count();
                        if($count > $user->daily_quest){
                            $extra = $count - $user->daily_quest;
                        }
                        $type = 2;
                    }else{
                        $type = 1;
                    }
                }else{
                    $type = 3;
                }
            }else{
                $type = 4;
            }
            TokenLogs::create([
                'user_id' => $user->id,
                'type' => $type,
                'extra' => $extra,
                'created_at' => $yesterday
            ]);
            $user->update([
                'is_daily_press' => 0,
                'is_extra_press' => 0,
            ]);
        }

        // calculate user task and give daily token (图片手)
        $users = User::whereHas('role', function ($query) {
                $query->where('id', 7);
        })->whereHas('projects', function ($query) {
            $query->where('project_id', Project::MINGSHUN);
        })->get();
        $yesterday = Carbon::now()->subDay();
        foreach($users as $user){
            $extra = 0;
            $assignedVideo = Video::where('cover_status', '!=', 2)->where('cover_assigned_to', $user->id)->first();
            if($user->is_daily_press){
                if(!$assignedVideo){
                    if($user->is_extra_press){
                        $count = Video::where('status', '!=', 1)->where('assigned_to', $user->id)
                            ->whereDate('first_approved_at', $yesterday)
                            ->whereNull('rereviewer_by')->count();
                        if($count > $user->daily_quest){
                            $extra = $count - $user->daily_quest;
                        }
                        $type = 2;
                    }else{
                        $type = 1;
                    }
                }else{
                    $type = 3;
                }
            }else{
                $type = 4;
            }
            TokenLogs::create([
                'user_id' => $user->id,
                'type' => $type,
                'extra' => $extra,
                'created_at' => $yesterday
            ]);
            $user->update([
                'is_daily_press' => 0,
                'is_extra_press' => 0,
            ]);
        }

        // calculate user task and give daily token (上传手)
        $users = User::whereHas('role', function ($query) {
            $query->where('id', 3);
        })->whereHas('projects', function ($query) {
            $query->where('project_id', Project::MINGSHUN);
        })->get();
        $today = Carbon::now();
        $yesterday = $today->subDay();
        foreach($users as $user){
            $extra = 0;
            $videoUploadedCount = Video::where('uploader', $user->id)
                ->whereDate('created_at', $yesterday)->count();
            if($videoUploadedCount == 0){
                $type = 4;
            }elseif($videoUploadedCount == $user->daily_quest2){
                $type = 1;
            }elseif($videoUploadedCount > $user->daily_quest2){
                $extra = $videoUploadedCount - $user->daily_quest2;
                $type = 2;
            }elseif($videoUploadedCount < $user->daily_quest2){
                $type = 3;
            }
            $today = Carbon::now();
            $yesterday = $today->subDay();
            TokenLogs::create([
                'user_id' => $user->id,
                'type' => $type,
                'extra' => $extra,
                'created_at' => $yesterday
            ]);
        }
    }
}
