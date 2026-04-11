<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cut_real_server_status_logs', function (Blueprint $table) {
            $table->string('namespace')->default('');
            $table->string('server')->default('');
        });
    
        Schema::table('cut_real_server_status_logs', function (Blueprint $table) {
            $table->dropColumn('cut_real_server_id');
        });

        Schema::dropIfExists('cut_real_servers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
