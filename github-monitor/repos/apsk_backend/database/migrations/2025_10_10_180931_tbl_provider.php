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
        Schema::create('tbl_provider', function (Blueprint $table) {
            $table->bigIncrements('provider_id')->comment('供应商ID');
            $table->unsignedBigInteger('gameplatform_id')->default(0)->comment('游戏平台');
            $table->integer('providertarget_id')->nullable()->comment('对方供应商ID');
            $table->string('provider_name')->comment('供应商名字');
            $table->string('android')->nullable()->collation('utf8mb4_unicode_ci')->comment('安卓名字');
            $table->string('ios')->nullable()->collation('utf8mb4_unicode_ci')->comment('IOS名字');
            $table->string('icon')->nullable()->collation('utf8mb4_unicode_ci')->comment('供应商icon');
            $table->string('icon_sm')->nullable()->collation('utf8mb4_unicode_ci')->comment('供应商icon（小）');
            $table->string('banner')->nullable()->comment('横幅');
            $table->string('download')->nullable()->comment('下载链接');
            $table->string('platform_type')->nullable()->comment('平台类型');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('gameplatform_id')
                ->references('gameplatform_id')
                ->on('tbl_gameplatform')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_provider');
    }
};
