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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('download_path')->nullable();
            $table->string('reason')->nullable();
            $table->text('others')->nullable();
            $table->unsignedInteger('status');
            $table->unsignedInteger('uploader');
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
        Schema::dropIfExists('posts');
    }
};
