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
        Schema::create('tbl_application', function (Blueprint $table) {
            $table->bigIncrements('application_id')->comment('软件ID');
            $table->string('application_name')->unique()->comment('软件名字');
            $table->string('platform')->comment('平台 安卓/苹果 ios/android');
            $table->string('version')->comment('版本');
            $table->string('latest_version')->comment('最新版本');
            $table->string('minimun_version')->comment('最低版本');
            $table->string('download_url')->nullable()->comment('下载地址');
            $table->string('type')->nullable()->comment('manager/shop/member');
            $table->longText('changelog')->nullable()->collation('utf8mb4_unicode_ci')->comment('更新内容');
            $table->integer('force_update')->default(0)->comment('强迫升级');
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
        Schema::dropIfExists('tbl_application');
    }
};
