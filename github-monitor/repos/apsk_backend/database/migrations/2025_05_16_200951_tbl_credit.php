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
        Schema::create('tbl_credit', function (Blueprint $table) {
            $table->bigIncrements('credit_id')->comment('交易ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('member_id')->comment('会员ID');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->unsignedBigInteger('gamemember_id')->nullable()->comment('游戏玩家ID');
            $table->unsignedBigInteger('payment_id')->nullable()->comment('⽀付ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->longText('orderid')->nullable()->comment('订单ID');
            $table->longText('transactionId')->nullable()->comment('交易ID');
            $table->unsignedBigInteger('bankaccount_id')->nullable()->comment('银行户口ID');
            $table->decimal('charge', 65, 2)->default(0.00)->comment('收费');
            $table->string('type', 20)->comment('交易类型 提现/充值 withdraw/deposit');
            $table->integer('isqr')->default(0)->comment('是否二维码');
            $table->decimal('amount', 65, 4)->default(0.0000)->comment('交易金额');
            $table->decimal('before_balance', 65, 4)->default(0.0000)->comment('交易前余额');
            $table->decimal('after_balance', 65, 4)->default(0.0000)->comment('交易后余额');
            $table->longText('reason')->nullable()->collation('utf8mb4_unicode_ci')
                  ->comment('拒绝理由');
            $table->timestamp('submit_on')->nullable()->comment('交易时间');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
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
        Schema::dropIfExists('tbl_credit');
    }
};
