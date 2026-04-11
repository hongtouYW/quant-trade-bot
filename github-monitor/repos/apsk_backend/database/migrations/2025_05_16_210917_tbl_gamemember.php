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
        Schema::create('tbl_gamemember', function (Blueprint $table) {
            $table->bigIncrements('gamemember_id')->comment('游戏玩家ID');
            $table->unsignedBigInteger('game_id')->nullable()->comment('游戏ID');
            $table->unsignedBigInteger('gameplatform_id')->nullable()->comment('游戏平台ID');
            $table->unsignedBigInteger('provider_id')->nullable()->comment('供应商ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->longText('uid')->nullable()->comment('平台玩家ID');
            $table->string('loginId')->nullable()->comment('平台登入ID');
            $table->string('login')->nullable()->comment('游戏登入');
            $table->longText('pass')->nullable()->comment('游戏密码');
            $table->string('name')->nullable()->comment('游戏称呼');
            $table->string('paymentpin', 20)->nullable()->comment('交易代码');
            $table->decimal('balance', 65, 4)->default(0.0000)->comment('游戏余额');
            $table->integer('has_balance')->default(0)->comment('游戏有余额，transfer需要拿api balance');
            $table->timestamp('lastlogin_on')->nullable()->comment('最后登入时间');
            $table->timestamp('lastsync_on')->nullable()->comment('最后同步时间');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->index(
                ['member_id', 'provider_id', 'status', 'delete'],
                'idx_member_provider_status_delete'
            );
            $table->index(
                ['gamemember_id', 'member_id', 'status', 'delete'],
                'idx_member_status_delete_gamemember'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_gamemember');
    }
};
