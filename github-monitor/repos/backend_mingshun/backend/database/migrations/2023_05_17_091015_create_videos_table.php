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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('resolution')->nullable();
            $table->string('size')->nullable();
            $table->string('path');
            $table->unsignedInteger('uploader');
            $table->unsignedInteger('status');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('first_approved_by')->nullable();
            $table->dateTime('first_approved_at')->nullable();
            $table->unsignedInteger('second_approved_by')->nullable();
            $table->dateTime('second_approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
