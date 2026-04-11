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
        Schema::create('tbl_countries', function (Blueprint $table) {
            $table->string('country_code', 3)->primary()->comment('国家ID');
            $table->string('country_name', 100)->unique()->comment('国家名字');
            $table->string('phone_code', 10)->nullable()->comment('电话代码');
            $table->integer('status')->default(1)->comment('0 - 屏蔽 1 - 活跃');
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
        Schema::dropIfExists('tbl_countries');
    }
};
