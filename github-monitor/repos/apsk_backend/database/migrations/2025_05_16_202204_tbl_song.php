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
        Schema::create('tbl_song', function (Blueprint $table) {
            $table->bigIncrements('song_id')->comment('歌曲ID');
            $table->string('song_name')->unique()->comment('歌曲名字');
            $table->unsignedBigInteger('artist_id')->nullable()->comment('歌手 artist_id');
            $table->unsignedBigInteger('creator')->nullable()->comment('创作人 artist_id');
            $table->string('album')->comment('专辑');
            $table->unsignedBigInteger('genre_id')->comment('曲风ID');
            $table->timestamp('published_on')->comment('发布日期时间');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
            
            $table->foreign('artist_id')
                  ->references('artist_id')
                  ->on('tbl_artist')
                  ->onDelete('cascade');
            $table->foreign('creator')
                  ->references('artist_id')
                  ->on('tbl_artist')
                  ->onDelete('cascade');
            $table->foreign('genre_id')
                  ->references('genre_id')
                  ->on('tbl_genre')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_song');
    }
};
