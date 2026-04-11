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
        Schema::create('tbl_question', function (Blueprint $table) {
            $table->bigIncrements('question_id')->comment('問題ID');
            $table->string('question_type', 50)->comment('问题分类');
            $table->string('type', 255)->default('user')->comment('user/manager/shop/member');
            $table->string('title', 50)->comment('问题主題');
            $table->longText('question_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('问题内容');
            $table->longText('picture')->nullable()->comment('图片');
            $table->unsignedBigInteger('related')->nullable()->comment('问题关联');
            $table->string('lang', 10)->default('en')->comment('语言en/zh');
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
        Schema::dropIfExists('tbl_question');
    }
};
