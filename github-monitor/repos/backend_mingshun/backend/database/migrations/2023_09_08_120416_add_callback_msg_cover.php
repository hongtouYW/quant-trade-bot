<?php

use App\Models\Project;
use App\Models\VideoChoose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $videoChooses = VideoChoose::whereNotNull('cut_callback_msg')->get();
        foreach($videoChooses as $videoChoose){
            $project = Project::findOrFail($videoChoose->project_id);
            $project_server = $project->servers->first();
            $ps_path = $project_server->path;
            $msg = json_decode($videoChoose->cut_callback_msg);
            if($msg->cover ?? ''){
                $msg->cover = $ps_path . "/" . $msg->cover;
            }
            $videoChoose->cut_callback_msg = json_encode($msg);
            $videoChoose->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
