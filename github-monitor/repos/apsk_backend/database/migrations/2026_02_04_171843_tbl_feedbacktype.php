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
        Schema::create('tbl_feedbacktype', function (Blueprint $table) {
            $table->bigIncrements('feedbacktype_id')->primary()->comment('反馈类型ID');
            $table->string('title')->nullable()->comment('反馈标题');
            $table->string('feedback_type')->comment('反馈类型');
            $table->longText('feedback_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('反馈类型详情');
            $table->string('type')->comment('类型shop/member');
            $table->integer('status')->default(1)->comment('状态 -1 - 活跃 0 - 关闭');
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
        Schema::dropIfExists('tbl_feedbacktype');
    }
};
