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
        Schema::create('tbl_gamelog', function (Blueprint $table) {
            $table->bigIncrements('gamelog_id')->primary()->comment('游戏历史ID');
            $table->longText('gamelogtarget_id')->nullable()->comment('平台投注历史ID');
            $table->unsignedBigInteger('gamemember_id')->comment('游戏玩家ID');
            $table->unsignedBigInteger('game_id')->comment('游戏ID');
            $table->longText('tableid')->nullable()->comment('桌子ID');
            $table->decimal('before_balance', 65, 4)->default(0.0000)->comment('交易前余额');
            $table->decimal('after_balance', 65, 4)->default(0.0000)->comment('交易后余额');
            $table->decimal('betamount', 65, 4)->default(0.0000)->comment('下注金额');
            $table->decimal('winloss', 65, 4)->default(0.0000)->comment('输赢金额');
            $table->longText('remark')->nullable()->comment('赛事描述');
            $table->string('error')->default(0)->comment('错误状态 0 - 是 1 - 不是');
            $table->timestamp('startdt')->nullable()->comment('开始时间');
            $table->timestamp('enddt')->nullable()->comment('结束时间');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
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
        Schema::dropIfExists('tbl_gamelog');
    }
};
