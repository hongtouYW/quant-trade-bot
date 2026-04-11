<?php

use App\Models\Video;
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
        $videoId = [];
        $japanhdv_videos = Video::where('source','japanhdv')->get();
        foreach($japanhdv_videos as $video){
            $videoId[] = $video->id;
            $video->status = 1;
            $video->first_approved_at = null;
            $video->first_approved_by = null;
            $video->assigned_to = null;
            $video->assigned_at = null;
            $video->save();
        }
        $tenshigao_videos = Video::where('source','tenshigao')->get();
        foreach($tenshigao_videos as $video){
            $videoId[] = $video->id;
            $video->status = 1;
            $video->first_approved_at = null;
            $video->first_approved_by = null;
            $video->assigned_to = null;
            $video->assigned_at = null;
            $video->save();
        }
        $baberotica_videos = Video::where('source','baberotica')->get();
        foreach($baberotica_videos as $video){
            $videoId[] = $video->id;
            $video->status = 1;
            $video->first_approved_at = null;
            $video->first_approved_by = null;
            $video->assigned_to = null;
            $video->assigned_at = null;
            $video->save();
        }
        $videoChooses = VideoChoose::whereIn('video_id', $videoId)->get();
        foreach($videoChooses as $videoChoose){
            $videoChoose->delete();
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
