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
        Schema::create('tbl_shoppin', function (Blueprint $table) {
            $table->string('shoppin_id')->primary()->collation('utf8mb4_unicode_ci')->comment('店铺顶置ID - （店铺ID-经理ID）');
            $table->unsignedBigInteger('shop_id')->comment('店铺ID');
            $table->unsignedBigInteger('manager_id')->comment('经理ID');
            $table->timestamp('created_on')->nullable()->comment('创建时间');  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_shoppin');
    }
};
