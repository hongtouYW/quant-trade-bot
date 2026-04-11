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
        Schema::create('tbl_invitation_history', function (Blueprint $table) {
            $table->bigIncrements('invitation_history_id')->comment('邀请ID');
            $table->string('invitecode', 10)->nullable()->comment('使用邀请码');
            $table->unsignedBigInteger('member_id')->comment('会员ID');
            $table->unsignedBigInteger('upline')->nullable()->comment('上线ID');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->timestamp('registered_on')->comment('邀请时间');
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
        Schema::dropIfExists('tbl_invitation_history');
    }
};
