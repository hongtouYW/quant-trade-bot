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
        Schema::create('tbl_score', function (Blueprint $table) {
            $table->bigIncrements('score_id')->primary()->comment('分数ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->string('type')->nullable()->default("vip")->comment('分数类型');
            $table->decimal('amount', 65, 4)->default(0.0000)->comment('存入分数');
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
        Schema::dropIfExists('tbl_score');
    }
};
