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
        Schema::create('tbl_otp', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('OTP ID');
            $table->string('login')->unique()->comment('用户登入');
            $table->string('password')->comment('用户密码');
            $table->string('type')->default('user')->comment('user/manager/shop/member');
            $table->string('verifyby', 20)->default('phone')->comment('phone/email/google');
            $table->string('code', 6)->comment('OTP');
            $table->integer('verified')->default(0)->comment('0-未验证,1-已验证');
            $table->string('module',50)->comment('模块');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->integer('attempts')->default(0)->comment('验证次数');
            $table->timestamp('last_attempt_on')->nullable()->comment('最后尝试时间');
            $table->timestamp('blocked_until')->nullable()->comment('被阻止直到');
            $table->timestamp('expires_on')->nullable()->comment('逾期时间');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_otp');
    }
};
