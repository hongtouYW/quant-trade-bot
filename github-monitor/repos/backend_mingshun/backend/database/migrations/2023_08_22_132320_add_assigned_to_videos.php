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
            $table->unsignedInteger('assigned_to')->nullable();
            $table->dateTime('assigned_at')->nullable();
        });

        $videos = Video::whereNotNull('first_approved_at')->get();
        foreach($videos as $video){
            $video->assigned_to = $video->first_approved_by;
            $video->assigned_at = $video->first_approved_at;
            $video->save();
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
