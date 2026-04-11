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
        Schema::create('tbl_shop', function (Blueprint $table) {
            $table->bigIncrements('shop_id')->comment('店铺ID');
            $table->string('shop_login')->unique()->comment('店铺登入');
            $table->longText('shop_pass')->comment('店铺密码');
            $table->string('shop_name')->unique()->comment('店铺名字');
            $table->string('area_code', 10)->comment('地区ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->decimal('principal')->default(0.00)->comment('当前本⾦');
            $table->decimal('balance', 65, 2)->default(0.00)->comment('当前现⾦');
            $table->string('devicekey')->nullable()->comment('设备密钥');
            $table->timestamp('lastlogin_on')->nullable()->comment('最后登入时间');
            $table->integer('GAstatus')->default(0)
                  ->comment('是否开启Google authentication 0 - 关闭 1 - 开启');
            $table->text('two_factor_secret')->nullable()->comment('Google authentication 登入码');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Google authentication 恢复码');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Google authentication 日期');
            $table->tinyInteger('can_deposit')->default(1)->comment('可以充值');
            $table->tinyInteger('can_withdraw')->default(1)->comment('可以提现支付');
            $table->tinyInteger('can_create')->default(1)->comment('可以创建账号');
            $table->tinyInteger('can_block')->default(1)->comment('可以拉黑账号');
            $table->tinyInteger('can_income')->default(1)->comment('可以显示总收入');
            $table->tinyInteger('can_view_credential')->default(1)->comment('店铺可看登入密码');
            $table->decimal('lowestbalance', 65, 2)->default(0.50)->comment('最低分通知提醒');
            $table->timestamp('lowestbalance_on')->nullable()->comment('最低分通知提醒编辑时间');
            $table->tinyInteger('no_withdrawal_fee')->default(0)->comment('店铺提现不需要5%');
            $table->tinyInteger('read_clear')->default(1)->comment('显示清账记录');
            $table->tinyInteger('alarm')->default(0)->comment('拉警报');
            $table->longText('reason')->nullable()->collation('utf8mb4_unicode_ci')->comment('关闭理由');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('经理ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->integer('status')->default(1)->comment('状态 0 - 关闭 1 - 开启');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('area_code')
                  ->references('area_code')
                  ->on('tbl_areas')
                  ->onDelete('cascade');

            $table->foreign('manager_id')
                  ->references('manager_id')
                  ->on('tbl_manager')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_shop');
    }
};
