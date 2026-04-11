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
        Schema::create('tbl_gameplatform', function (Blueprint $table) {
            $table->bigIncrements('gameplatform_id')->comment('游戏平台ID');
            $table->string('gameplatform_name')->comment('游戏平台');
            $table->string('api')->nullable()->comment('游戏回调API');
            $table->decimal('commission', 65, 2)->default(0.00)->comment('默认佣金');
            $table->string('icon')->nullable()->collation('utf8mb4_unicode_ci')->comment('游戏平台icon');
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
        Schema::dropIfExists('tbl_gameplatform');
    }
};
