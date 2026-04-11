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
        Schema::create('tbl_noticepublic', function (Blueprint $table) {
            $table->bigIncrements('notice_id')->comment('公告ID');
            $table->string('recipient_type', 50)->comment('收件者类型user/manager/shop/member');
            $table->string('title', 50)->comment('公告主題');
            $table->longText('desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('公告内容');
            $table->string('lang', 10)->default('en')->comment('语言en/zh');
            $table->timestamp('start_on')->nullable()->comment('开始时间');
            $table->timestamp('end_on')->nullable()->comment('结束时间');
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
        Schema::dropIfExists('tbl_noticepublic');
    }
};
