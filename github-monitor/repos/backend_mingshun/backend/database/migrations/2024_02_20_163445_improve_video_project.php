<?php

use App\Models\Config;
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
        Schema::table('projects', function (Blueprint $table) {
            $table->integer('daily_cut_quota')->default(20);
            $table->integer('daily_cut')->default(0);
        });

        Schema::table('video_chooses', function (Blueprint $table) {
            $table->integer('recut_time')->default(0);
        });
        Config::create([
            'key' => 'default_project_daily_cut_quota',
            'value' => 20,
            'description' => '项目每日切片限制',
        ]);
        Config::create([
            'key' => 'recut_times',
            'value' => 1,
            'description' => '重新切片次数',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
