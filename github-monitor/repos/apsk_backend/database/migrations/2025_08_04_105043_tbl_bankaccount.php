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
        Schema::create('tbl_bankaccount', function (Blueprint $table) {
            $table->bigIncrements('bankaccount_id')->comment('银行户口ID');
            $table->unsignedBigInteger('member_id')->comment('会员ID');
            $table->unsignedBigInteger('bank_id')->comment('银行ID');
            $table->string('bank_account')->comment('银行户口');
            $table->string('bank_full_name')->comment('银行户口名字');
            $table->integer('fastpay')->default(0)->comment('快速支付');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
            
            $table->foreign('member_id')
                  ->references('member_id')
                  ->on('tbl_member')
                  ->onDelete('cascade');
                  
            $table->foreign('bank_id')
                  ->references('bank_id')
                  ->on('tbl_bank')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_bankaccount');
    }
};
