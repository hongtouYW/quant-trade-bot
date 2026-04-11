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
        Schema::create('tbl_paymentgateway', function (Blueprint $table) {
            $table->bigIncrements('payment_id')->comment('⽀付ID');
            $table->string('payment_name')->comment('⽀付管道');
            $table->string('icon')->nullable()->comment('图像');
            $table->string('amount_type')->nullable()->comment('限定金额 30/50/60/100/⾃选');
            $table->string('type', 50)->nullable()->comment('支付分类');
            $table->decimal('min_amount', 65, 2)->default(0.00)->comment('最低金额');
            $table->decimal('max_amount', 65, 2)->default(0.00)->comment('最高金额');
            $table->integer('status')->default(1)->comment('状态 0 - 关 1 - 开');
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
        Schema::dropIfExists('tbl_paymentgateway');
    }
};
