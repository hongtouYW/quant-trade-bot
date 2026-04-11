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
        Schema::create('tbl_member', function (Blueprint $table) {
            $table->bigIncrements('member_id')->comment('会员ID');
            $table->string('member_login')->unique()->comment('会员登入');
            $table->longText('member_pass')->comment('会员密码');
            $table->string('member_name')->unique()->comment('会员名字');
            $table->string('full_name')->nullable()->comment('会员全名');
            $table->string('area_code', 10)->nullable()->comment('地区ID');
            $table->string('prefix')->nullable()->comment('前缀');
            $table->string('phone')->unique()->comment('电话号码');
            $table->string('email')->nullable()->unique()->comment('会员电邮');
            $table->string('wechat')->nullable()->unique()->comment('绑定微信');
            $table->string('whatsapp')->nullable()->unique()->comment('绑定国际即时通讯');
            $table->string('facebook')->nullable()->unique()->comment('绑定面子书');
            $table->string('telegram')->nullable()->unique()->comment('绑定纸飞机');
            $table->string('avatar')->nullable()->comment('会员头像');
            $table->decimal('balance', 65, 4)->default(0.0000)->comment('余额');
            $table->string('devicekey')->nullable()->comment('设备密钥');
            $table->longText('devicemeta')->nullable()->comment('设备元数据');
            $table->string('ip')->nullable()->comment('IP地址');
            $table->unsignedBigInteger('vip')->default(0)->comment('会员等级');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->timestamp('dob')->nullable()->comment('生日日期');
            $table->timestamp('lastlogin_on')->nullable()->comment('最后登入时间');
            $table->integer('alarm')->default(0)
                  ->comment('只限音乐频道 0 - 关闭 1 - 开启');
            $table->longText('reason')->nullable()->collation('utf8mb4_unicode_ci')->comment('屏蔽理由');
            $table->integer('GAstatus')->default(0)
                  ->comment('是否开启Google authentication 0 - 关闭 1 - 开启');
            $table->longText('two_factor_secret')->nullable()->comment('Google authentication 登入码');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Google authentication 恢复码');
            $table->timestamp('two_factor_confirmed_at')->nullable()->comment('Google authentication 日期');
            $table->integer('bindphone')->default(1)->comment('0 - 未绑定 1 - 绑定');
            $table->integer('bindemail')->default(0)->comment('0 - 未绑定 1 - 绑定');
            $table->integer('bindgoogle')->default(0)->comment('0 - 未绑定 1 - 绑定');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('vip')
                  ->references('vip_id')
                  ->on('tbl_vip')
                  ->onDelete('cascade');
            $table->foreign('area_code')
                  ->references('area_code')
                  ->on('tbl_areas')
                  ->onDelete('cascade');
            $table->foreign('shop_id')
                  ->references('shop_id')
                  ->on('tbl_shop')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_member');
    }
};
