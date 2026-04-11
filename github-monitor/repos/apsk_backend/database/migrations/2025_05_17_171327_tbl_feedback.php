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
        Schema::create('tbl_feedback', function (Blueprint $table) {
            $table->bigIncrements('feedback_id')->comment('反馈ID');
            $table->unsignedBigInteger('feedbacktype_id')->comment('反馈类型ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->unsignedBigInteger('shop_id')->nullable()->comment('店铺ID');
            $table->unsignedBigInteger('manager_id')->nullable()->comment('经理ID');
            $table->longText('feedback_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('反馈描述');
            $table->string('photo')->nullable()->collation('utf8mb4_unicode_ci')->comment('上传图片');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
            
            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('tbl_agent')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_feedback');
    }
};
