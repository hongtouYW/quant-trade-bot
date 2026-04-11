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
        Schema::create('tbl_agreement', function (Blueprint $table) {
            $table->bigIncrements('agreement_id')->comment('协议ID');
            $table->string('title', 50)->comment('主題');
            $table->longText('picture')->nullable()->collation('utf8mb4_unicode_ci')->comment('图片');
            $table->longText('desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('内容');
            $table->longText('url')->nullable()->collation('utf8mb4_unicode_ci')->comment('链接');
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
        Schema::dropIfExists('tbl_agreement');
    }
};
