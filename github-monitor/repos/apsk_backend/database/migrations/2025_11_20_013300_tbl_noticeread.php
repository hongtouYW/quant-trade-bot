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
        Schema::create('tbl_noticeread', function (Blueprint $table) {
            $table->bigIncrements('noticeread_id')->comment('公告读取ID');
            $table->unsignedBigInteger('notice_id')->nullable()->comment('公告ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('经理ID');
            $table->timestamp('read_on')->nullable()->comment('读取时间');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
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
        Schema::dropIfExists('tbl_noticeread');
    }
};
