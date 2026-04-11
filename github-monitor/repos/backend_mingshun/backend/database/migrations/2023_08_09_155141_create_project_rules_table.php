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
        Schema::create('project_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('resolution_option')->nullable();
            $table->string('webp_enable')->default(0);
            $table->string('webp_start')->nullable();
            $table->string('webp_interval')->nullable();
            $table->string('webp_count')->nullable();
            $table->string('webp_length')->nullable();
            $table->string('skip_enable')->default(0);
            $table->string('skip_head')->nullable();
            $table->string('skip_back')->nullable();
            $table->string('head_enable')->default(0);
            $table->string('head_video')->nullable();
            $table->string('ad_enable')->default(0);
            $table->string('ad_image')->nullable();
            $table->string('ad_start')->nullable();
            $table->string('logo_enable')->default(0);
            $table->string('logo_image')->nullable();
            $table->string('logo_position')->nullable();
            $table->string('logo_padding')->nullable();
            $table->string('logo_scale')->nullable();
            $table->string('font_enable')->default(0);
            $table->string('font_text')->nullable();
            $table->string('font_color')->nullable();
            $table->string('font_size')->nullable();
            $table->string('font_position')->nullable();
            $table->string('font_interval')->nullable();
            $table->string('font_scroll')->nullable();
            $table->string('font_border')->nullable();
            $table->string('font_shadow')->nullable();
            $table->string('font_space')->nullable();
            $table->string('preview_enable')->default(0);
            $table->string('preview_interval')->nullable();
            $table->string('m3u8_enable')->default(0);
            $table->string('m3u8_encrypt')->nullable();
            $table->string('m3u8_interval')->nullable();
            $table->string('m3u8_bitrate')->nullable();
            $table->string('m3u8_fps')->nullable();
            $table->string('callback_url');
            $table->unsignedInteger('project_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_rules');
    }
};
