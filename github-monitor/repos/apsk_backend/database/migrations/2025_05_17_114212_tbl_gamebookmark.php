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
        Schema::create('tbl_gamebookmark', function (Blueprint $table) {
            $table->bigIncrements('gamebookmark_id')->comment('游戏收藏ID');
            $table->string('gamebookmark_name')->comment('游戏收藏名字');
            $table->unsignedBigInteger('game_id')->comment('游戏ID');
            $table->unsignedBigInteger('member_id')->comment('会员ID');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_gamebookmark');
    }
};
