<?php

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
        Schema::table('video_tags', function (Blueprint $table) {
            $table->id()->before('video_id');
        });
        Schema::table('video_types', function (Blueprint $table) {
            $table->id()->before('video_id');
        });
        Schema::table('post_tags', function (Blueprint $table) {
            $table->id()->before('post_id');
        });
        Schema::table('post_types', function (Blueprint $table) {
            $table->id()->before('post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
