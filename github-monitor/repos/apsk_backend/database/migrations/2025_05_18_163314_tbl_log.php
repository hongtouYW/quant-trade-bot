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
        Schema::create('tbl_log', function (Blueprint $table) {
            $table->bigIncrements('log_id')->comment('日志ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('会员/店铺/经理ID');
            $table->string('area_code', 10)->nullable()->comment('地区ID');
            $table->string('log_type', 20)->comment('日志类型 ⻆⾊筛选 ⽤户名字/地区/店铺/经理/会员/游戏');
            $table->string('log_text', 50)->comment('关键词 ⻆⾊筛选 ⽤户名字/地区/店铺/经理/会员/游戏');
            $table->longText('log_desc')->collation('utf8mb4_unicode_ci')->comment('日志详情');
            $table->longText('log_api')->nullable()->collation('utf8mb4_unicode_ci')->comment('日志API');
            $table->longText('location')->nullable()->comment('🌎地理位置');
            $table->longText('device')->nullable()->comment('💻设备');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_log');
    }
};
