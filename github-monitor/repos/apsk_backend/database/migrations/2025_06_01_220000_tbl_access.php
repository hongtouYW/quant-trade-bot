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
        Schema::create('tbl_access', function (Blueprint $table) {
            $table->bigIncrements('access_id')->comment('权限ID');
            $table->string('user_role', 20)->nullable()->collation('utf8mb4_unicode_ci')
                  ->comment('用户角色 superadmin - 超级管理员 admin - 管理员');
            $table->unsignedBigInteger('module_id')->comment('管理ID');
            $table->boolean('can_view')->default(false)->comment('权限查看');
            $table->boolean('can_edit')->default(false)->comment('权限编辑');
            $table->boolean('can_delete')->default(false)->comment('权限删除');
            $table->integer('status')->default(1)->comment('状态 0 - 屏蔽 1 - 活跃');
            $table->integer('delete')->default(0)->comment('1 - 已删除');
            $table->timestamp('created_on')->nullable()->comment('创建时间');
            $table->timestamp('updated_on')->nullable()->comment('编辑时间');

            $table->foreign('user_role')
                  ->references('role_name')
                  ->on('tbl_role')
                  ->onDelete('cascade');
            $table->foreign('module_id')
                  ->references('module_id')
                  ->on('tbl_module')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_access');
    }
};
