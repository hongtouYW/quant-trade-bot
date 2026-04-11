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
        Schema::create('tbl_permission', function (Blueprint $table) {
            $table->bigIncrements('permission_id')->comment('权限ID');
            $table->unsignedBigInteger('permission_user')->comment('权限用户 user_id manager_id shop_id member_id');
            $table->string('user_type')->nullable()->collation('utf8mb4_unicode_ci')->comment('权限用户类型user/manager/shop/member');
            $table->unsignedBigInteger('permission_target')->comment('管理ID');
            $table->string('target_type')->nullable()->collation('utf8mb4_unicode_ci')->comment('管理用户类型user/manager/shop/member');
            $table->boolean('can_view')->default(false)->comment('权限查看');
            $table->boolean('can_edit')->default(false)->comment('权限编辑');
            $table->boolean('can_delete')->default(false)->comment('权限删除');
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
        Schema::dropIfExists('tbl_permission');
    }
};
