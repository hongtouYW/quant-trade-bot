<?php

use App\Models\Video;
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
        $source_chg = ['jiali','japanhdv','beberotica','tenshigao','avidolz','teenthais','hd-access','baberoticavr','suckmevr'];
        $source_chg_videos = Video::whereIn('source', $source_chg)->get();
        foreach($source_chg_videos as $video){
            $videoId[] = $video->id;
            $video->source = 'rv_' . $video->source;
            $video->save();
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
