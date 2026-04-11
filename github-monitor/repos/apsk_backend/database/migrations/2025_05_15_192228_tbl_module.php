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
        Schema::create('tbl_module', function (Blueprint $table) {
            $table->bigIncrements('module_id')->comment('管理ID');
            $table->string('module_name')->unique()->comment('管理名字'); 
            $table->longText('module_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('管理详情');
            $table->string('section')->comment('类型');
            $table->string('controller')->unique()->comment('控制器');
            $table->tinyInteger('has_tab')->default(0)->comment('应用界面标签');
            $table->string('tab_main')->nullable()->comment('应用界面标签-优先');
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
        Schema::dropIfExists('tbl_module');
    }
};
