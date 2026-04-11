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
        Schema::create('tbl_performance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->unsignedBigInteger('downline')->nullable()->comment('下线');
            $table->unsignedBigInteger('upline')->nullable()->comment('上线');
            $table->unsignedBigInteger('commissionrank_id')->nullable()->comment('佣金等级ID');
            $table->date('performance_date')->comment('当天表现');
            $table->decimal('sales_amount', 65, 4)->default(0.0000)->comment('销售金额');
            $table->decimal('commission_amount', 65, 4)->default(0.0000)->comment('佣金金额');
            $table->decimal('before_balance', 65, 4)->default(0.0000)->comment('交易前余额');
            $table->decimal('after_balance', 65, 4)->default(0.0000)->comment('交易后余额');
            $table->text('notes')->nullable()->comment('业绩记录');
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
        Schema::dropIfExists('tbl_performance');
    }
};
