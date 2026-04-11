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
        Schema::create('tbl_gametype', function (Blueprint $table) {
            $table->bigIncrements('gametype_id')->comment('游戏分类ID');
            $table->string('type_name')->unique()->collation('utf8mb4_unicode_ci')
                  ->comment('分类名字');
            $table->longText('type_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('分类详情');
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
        Schema::dropIfExists('tbl_gametype');
    }
};
