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
        Schema::create('tbl_ipblock', function (Blueprint $table) {
            $table->bigIncrements('ipblock_id')->primary()->comment('IP屏蔽ID');
            $table->string('ip')->comment('IP地址');
            $table->string('reason')->default(0)->comment('屏蔽理由');
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
        Schema::dropIfExists('tbl_ipblock');
    }
};
