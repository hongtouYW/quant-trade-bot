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
        Schema::table('tbl_application', function (Blueprint $table) {
            $table->string('download_url')->nullable()->after('version')->comment('下载地址');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_application', function (Blueprint $table) {
            $table->dropColumn('download_url');
        });
    }
};
