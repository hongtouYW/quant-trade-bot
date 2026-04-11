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
        Schema::create('tbl_turnover', function (Blueprint $table) {
            $table->bigIncrements('turnover_id')->primary()->comment('打码量ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->decimal('amount', 65, 4)->default(0.0000)->comment('打码量');
            $table->decimal('winamount', 65, 4)->default(0.0000)->comment('赢金额');
            $table->decimal('loseamount', 65, 4)->default(0.0000)->comment('输金额');
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
        Schema::dropIfExists('tbl_turnover');
    }
};
