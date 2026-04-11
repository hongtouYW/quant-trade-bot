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
        Schema::create('tbl_gamepoint', function (Blueprint $table) {
            $table->bigIncrements('gamepoint_id')->comment('游戏分数ID');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->unsignedBigInteger('gamemember_id')->nullable()->comment('游戏玩家ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->longText('orderid')->nullable()->comment('订单ID');
            $table->string('type', 20)->comment('交易分类 bonus/reward/reload/withdraw');
            $table->string('ip', 20)->nullable()->comment('IP地址');
            $table->decimal('amount', 65, 4)->default(0.0000)->comment('交易金额');
            $table->decimal('before_balance', 65, 4)->default(0.0000)->comment('交易前余额');
            $table->decimal('after_balance', 65, 4)->default(0.0000)->comment('交易后余额');
            $table->timestamp('start_on')->nullable()->comment('开始时间');
            $table->timestamp('end_on')->nullable()->comment('结束时间');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->integer('status')->default(1)->comment('状态 -1 - 失败 0 - 进行中 1 - 成功');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('shop_id')
                  ->references('shop_id')
                  ->on('tbl_shop')
                  ->onDelete('cascade');

            $table->index(
                ['gamemember_id', 'type', 'status', 'gamepoint_id'],
                'idx_gamemember_type_status_gamepoint'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_gamepoint');
    }
};
