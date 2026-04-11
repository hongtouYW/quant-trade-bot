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
        Schema::table('video_chooses', function (Blueprint $table) {
            $table->unsignedInteger('video_id');
            $table->dropColumn('user_id');
            $table->dropColumn('status');
            $table->dropColumn('updated_at');
            $table->dropColumn('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_chooses', function (Blueprint $table) {
            //
        });
    }
};
