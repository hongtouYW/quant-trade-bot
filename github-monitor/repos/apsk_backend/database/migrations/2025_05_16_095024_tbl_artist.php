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
        Schema::create('tbl_artist', function (Blueprint $table) {
            $table->bigIncrements('artist_id')->comment('创作人ID');
            $table->string('artist_name')->comment('创作人名字');
            $table->longText('artist_desc')->nullable()->collation('utf8mb4_unicode_ci')->comment('创作人简介');
            $table->unsignedBigInteger('genre_id')->comment('创作分类');
            $table->string('country_code', 3)->comment('国籍');
            $table->date('dob')->comment('出生日期');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');
            
            $table->foreign('genre_id')
                  ->references('genre_id')
                  ->on('tbl_genre')
                  ->onDelete('cascade');
            $table->foreign('country_code')
                  ->references('country_code')
                  ->on('tbl_countries')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_artist');
    }
};
