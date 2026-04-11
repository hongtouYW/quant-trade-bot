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
        Schema::table('cut_servers', function (Blueprint $table) {
            $table->string('idc')->nullable()->after('redis_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cut_servers', function (Blueprint $table) {
            $table->dropColumn('idc');
        });
    }
};
