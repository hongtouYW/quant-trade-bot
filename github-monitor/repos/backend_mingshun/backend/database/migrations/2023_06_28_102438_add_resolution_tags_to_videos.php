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
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('resolution_tag')->nullable();
        });

        foreach(Video::all() as $video){
            $resolution = $video->resolution;
            $temp = explode("x", $resolution);
            if($temp[1] ?? ''){
                $resolution_tag = 4;
                foreach(Video::RESOLUTION_FILTER_RANGE as $key => $range){
                    if($temp[1] >= $range){
                        $resolution_tag = $key;
                        break;
                    }
                }
                $video->resolution_tag = $resolution_tag;
                $video->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            //
        });
    }
};
