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
        Schema::create('tbl_areas', function (Blueprint $table) {
            $table->string('area_code', 10)->primary()->comment('地区ID');
            $table->string('country_code', 3)->comment('国家ID');
            $table->string('state_code', 10)->comment('州ID');
            $table->string('area_name', 100)->comment('地区名字');
            $table->string('area_type', 100)->comment('地区类型');
            $table->string('postal_code', 20)->nullable()->comment('Postal/ZIP');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('代理商ID');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('country_code')
                  ->references('country_code')
                  ->on('tbl_countries')
                  ->onDelete('cascade');
            $table->foreign('state_code')
                  ->references('state_code')
                  ->on('tbl_states')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_areas');
    }
};
