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
        Schema::create('tbl_bank', function (Blueprint $table) {
            $table->bigIncrements('bank_id')->comment('银行ID'); 
            $table->string('bank_name')->unique()->comment('银行名字');
            $table->integer('fpaybank_id')->nullable()->comment('FPay银行ID');
            $table->string('superpaybankcode', 50)->unique()->nullable()->comment('Super Pay 银行代码');
            $table->string('icon')->nullable()->comment('银行icon');
            $table->string('api')->nullable()->comment('银行回调API'); 
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
        Schema::dropIfExists('tbl_bank');
    }
};
