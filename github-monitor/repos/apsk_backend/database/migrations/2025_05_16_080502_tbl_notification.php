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
        Schema::create('tbl_notification', function (Blueprint $table) {
            $table->bigIncrements('notification_id')->comment('通知ID');
            $table->unsignedBigInteger('sender_id')->nullable()->comment('发送者ID');
            $table->string('sender_type')->nullable()->comment('user/manager/shop/member');
            $table->unsignedBigInteger('recipient_id')->nullable()->comment('收件者ID');
            $table->string('recipient_type')->nullable()->comment('user/manager/shop/member');
            $table->string('notification_type', 50)->comment('通知分类 admin,member,manager,shop,event,version,game,alert');
            $table->string('title', 50)->comment('通知主題');
            $table->longText('notification_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('通知内容');
            $table->integer('is_read')->default(0)->comment('读取状态 0 - 未读 1 - 已读');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->longText('firebase_error')->nullable()->collation('utf8mb4_unicode_ci')->comment('Firebase错误信息');
            $table->integer('status')->default(1)->comment('状态 0 - 等待 1 - 成功 -1 - 失败');
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
        Schema::dropIfExists('tbl_notification');
    }
};
