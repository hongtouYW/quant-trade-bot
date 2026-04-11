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
        Schema::create('tbl_user', function (Blueprint $table) {
            $table->bigIncrements('user_id')->comment('用户ID');
            $table->string('user_login')->unique()->comment('用户登入');
            $table->longText('user_pass')->comment('用户密码');
            $table->string('user_name')->unique()->comment('用户名字');
            $table->string('user_role', 20)->nullable()->collation('utf8mb4_unicode_ci')
                  ->comment('用户角色 superadmin - 超级管理员 admin - 管理员');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->string('state_code', 10)->comment('州ID');
            $table->string('area_code', 10)->nullable()->comment('地区ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->timestamp('lastlogin_on')->nullable()->comment('最后登入时间');
            $table->integer('GAstatus')->default(0)
                  ->comment('是否开启Google authentication 0 - 关闭 1 - 开启');
            $table->text('two_factor_secret')->nullable()->comment('Google authentication 登入码');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Google authentication 恢复码');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Google authentication 日期');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('user_role')
                  ->references('role_name')
                  ->on('tbl_role')
                  ->onDelete('cascade');
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
        Schema::dropIfExists('tbl_user');
    }
};
