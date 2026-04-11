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
        Schema::create('tbl_game', function (Blueprint $table) {
            $table->bigIncrements('game_id')->comment('游戏ID');
            $table->unsignedBigInteger('gameplatform_id')->default(0)->comment('游戏平台');
            $table->string('game_name')->unique()->comment('游戏名字');
            $table->longText('game_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('游戏详情');
            $table->unsignedBigInteger('provider_id')->nullable()->collation('utf8mb4_unicode_ci')->comment('供应商ID');
            $table->unsignedBigInteger('gametarget_id')->nullable()->collation('utf8mb4_unicode_ci')->comment('对方游戏ID');
            $table->unsignedBigInteger('tag_id')->nullable()->collation('utf8mb4_unicode_ci')->comment('标记ID');
            $table->string('android')->nullable()->collation('utf8mb4_unicode_ci')->comment('安卓名字');
            $table->string('ios')->nullable()->collation('utf8mb4_unicode_ci')->comment('IOS名字');
            $table->string('icon')->nullable()->collation('utf8mb4_unicode_ci')->comment('游戏icon');
            $table->string('icon_zh')->nullable()->collation('utf8mb4_unicode_ci')->comment('游戏icon-zh');
            $table->string('banner')->nullable()->comment('横幅');
            $table->string('api')->nullable()->comment('游戏回调API');
            $table->unsignedBigInteger('type')->comment('游戏分类ID 电⼦，热⻔，捕⻥，体育，真⼈');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('type')
                  ->references('gametype_id')
                  ->on('tbl_gametype')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_game');
    }
};
