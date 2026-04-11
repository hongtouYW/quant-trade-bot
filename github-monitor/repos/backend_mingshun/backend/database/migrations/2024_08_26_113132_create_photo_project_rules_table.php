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
        Schema::create('photo_project_rules', function (Blueprint $table) {
            $table->id();
            $table->string('font_enable')->default(0);
            $table->unsignedInteger('font_position')->nullable();
            $table->integer('font_borderSpace')->nullable();
            $table->string('font_fontName')->nullable();
            $table->integer('font_fontSize')->nullable();
            $table->string('font_fontColor')->nullable();
            $table->integer('font_lineSpace')->nullable();
            $table->string('font_text1')->nullable();
            $table->string('font_text2')->nullable();
            $table->string('font_text3')->nullable();
            $table->string('icon_enable')->default(0);
            $table->string('icon_path')->nullable();
            $table->string('icon_position')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_project_rules');
    }
};
