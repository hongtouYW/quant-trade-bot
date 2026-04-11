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
        Schema::create('tbl_promotion', function (Blueprint $table) {
            $table->bigIncrements('promotion_id')->comment('优惠ID');
            $table->unsignedBigInteger('promotiontype_id')->nullable()->comment('优惠分类ID');
            $table->string('title', 50)->comment('优惠主題');
            $table->longText('promotion_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('优惠内容');
            $table->unsignedBigInteger('vip_id')->nullable()->comment('会员等级');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->decimal('amount', 65, 2)->nullable()->comment('优惠金额');
            $table->decimal('percent', 65, 2)->nullable()->comment('优惠百分比');
            $table->longText('photo')->nullable()->comment('优惠图片');
            $table->tinyInteger('newbie')->default(0)->comment('优惠新手 0 - 不是 1 - 是');
            $table->string('url', 255)->nullable()->comment('优惠链接');
            $table->string('lang', 10)->default('en')->comment('语言en/zh');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('vip_id')
                  ->references('vip_id')
                  ->on('tbl_vip')
                  ->onDelete('cascade');

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
        Schema::dropIfExists('tbl_promotion');
    }
};
