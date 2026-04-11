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
        Schema::create('tbl_manager', function (Blueprint $table) {
            $table->bigIncrements('manager_id')->comment('经理ID');
            $table->string('manager_login')->unique()->comment('经理登入');
            $table->longText('manager_pass')->comment('经理密码');
            $table->string('manager_name')->unique()->comment('经理名字');
            $table->string('full_name')->nullable()->comment('经理全名');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->string('state_code', 10)->comment('州ID');
            $table->string('area_code', 10)->nullable()->comment('地区ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->string('phone')->nullable()->comment('电话号码');
            $table->decimal('balance', 65, 2)->default(0.00)->comment('当前现⾦');
            $table->decimal('principal', 65, 2)->default(0.00)->comment('当前本⾦');
            $table->string('devicekey')->nullable()->comment('设备密钥');
            $table->timestamp('lastlogin_on')->nullable()->comment('最后登入时间');
            $table->integer('GAstatus')->default(0)
                  ->comment('是否开启Google authentication 0 - 关闭 1 - 开启');
            $table->text('two_factor_secret')->nullable()->comment('Google authentication 登入码');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Google authentication 恢复码');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Google authentication 日期');
            $table->tinyInteger('can_view_credential')->default(1)->comment('经理可看登入密码');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
            
            $table->foreign('area_code')
                  ->references('area_code')
                  ->on('tbl_areas')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_manager');
    }
};
