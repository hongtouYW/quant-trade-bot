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
        $japan = 8;
        $europe = 7;
        $avjiali_videos = Video::where('source','avjiali')->get();
        foreach($avjiali_videos as $video){
            $video->types()->sync([$japan]);
        }
        $japanhdv_videos = Video::where('source','japanhdv')->get();
        foreach($japanhdv_videos as $video){
            $video->types()->sync([$japan]);
        }
        $tenshigao_videos = Video::where('source','tenshigao')->get();
        foreach($tenshigao_videos as $video){
            $video->types()->sync([$japan]);
        }
        $baberotica_videos = Video::where('source','baberotica')->get();
        foreach($baberotica_videos as $video){
            $video->types()->sync([$europe]);
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
