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
        Schema::create('tbl_gameplatformaccess', function (Blueprint $table) {
            $table->bigIncrements('gameplatformaccess_id')->comment('游戏平台权限ID');
            $table->unsignedBigInteger('gameplatform_id')->comment('游戏平台ID');
            $table->unsignedBigInteger('agent_id')->comment('代理商ID');
            $table->decimal('commission', 65, 2)->default(0.00)->comment('佣金');
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
        Schema::dropIfExists('tbl_gameplatformaccess');
    }
};
