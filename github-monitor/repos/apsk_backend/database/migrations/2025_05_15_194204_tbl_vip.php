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
        Schema::create('tbl_vip', function (Blueprint $table) {
            $table->bigIncrements('vip_id')->comment('等级ID');
            $table->string('vip_name')->comment('等级名字');
            $table->longText('vip_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('等级详情');
            $table->integer('lvl')->default(0)->comment('等级');
            $table->string('type')->comment('等级分类');
            $table->string('reward')->comment('奖励');
            $table->string('icon')->comment('icon');
            $table->decimal('firstbonus', 65, 2)->default(0.00)->comment('普级奖金');
            $table->decimal('dailybonus', 65, 2)->default(0.00)->comment('日俸禄');
            $table->decimal('weeklybonus', 65, 2)->default(0.00)->comment('周俸禄');
            $table->decimal('monthlybonus', 65, 2)->default(0.00)->comment('月俸禄');
            $table->decimal('min_amount', 65, 2)->default(0.00)->comment('最低金额');
            $table->decimal('max_amount', 65, 2)->default(0.00)->comment('最高金额');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
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
        Schema::dropIfExists('tbl_vip');
    }
};
