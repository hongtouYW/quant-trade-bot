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
        Schema::create('tbl_agentcredit', function (Blueprint $table) {
            $table->bigIncrements('agentcredit_id')->primary()->comment('代理商流水ID');
            $table->unsignedBigInteger('agent_id')->comment('代理商ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('manager_id')->comment('经理ID');
            $table->unsignedBigInteger('shop_id')->comment('店铺ID');
            $table->unsignedBigInteger('member_id')->comment('会员ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->decimal('amount', 65, 2)->default(0.00)->comment('交易金额');
            $table->decimal('before_balance', 65, 2)->default(0.00)->comment('交易前余额');
            $table->decimal('after_balance', 65, 2)->default(0.00)->comment('交易后余额');
            $table->string('type', 20)->comment('交易类型 提现/充值 withdraw/deposit');
            $table->longText('reason')->nullable()->collation('utf8mb4_unicode_ci')
                  ->comment('拒绝理由');
            $table->timestamp('submit_on')->nullable()->comment('交易时间');
            $table->integer('status')->default(1)->comment('状态 -1 - 拒绝 0 - 审核中 1 - 批准');
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
        Schema::dropIfExists('tbl_agentcredit');
    }
};
