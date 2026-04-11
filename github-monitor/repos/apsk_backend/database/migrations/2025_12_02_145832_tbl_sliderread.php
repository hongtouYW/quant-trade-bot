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
        Schema::create('tbl_sliderread', function (Blueprint $table) {
            $table->bigIncrements('sliderread_id')->comment('跑马灯读取ID');
            $table->unsignedBigInteger('slider_id')->nullable()->comment('跑马灯ID');
            $table->unsignedBigInteger('member_id')->nullable()->comment('会员ID');
            $table->timestamp('read_on')->nullable()->comment('读取时间');
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
        Schema::dropIfExists('tbl_sliderread');
    }
};
