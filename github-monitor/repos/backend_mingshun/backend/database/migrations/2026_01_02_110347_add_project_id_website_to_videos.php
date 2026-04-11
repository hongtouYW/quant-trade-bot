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
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedInteger('project_id')->nullable();
            $table->text('website')->nullable();
        });

        Schema::table('video_choose_project_types', function (Blueprint $table) {
            $table->unsignedInteger('video_id')->nullable();
        });

        Schema::table('project_types', function (Blueprint $table) {
            $table->text('show_name')->nullable();
            $table->integer('is_show')->default(0);
        });
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
