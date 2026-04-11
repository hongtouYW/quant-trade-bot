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
        Schema::create('tbl_promotiontype', function (Blueprint $table) {
            $table->bigIncrements('promotiontype_id')->comment('优惠分类ID');
            $table->string('promotion_type', 50)->comment('优惠分类');
            $table->string('event', 255)->comment('发动事件');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->decimal('amount', 65, 2)->nullable()->comment('优惠分类金额');
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
        Schema::dropIfExists('tbl_promotiontype');
    }
};
