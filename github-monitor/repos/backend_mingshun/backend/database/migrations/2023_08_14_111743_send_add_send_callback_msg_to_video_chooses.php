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
            $table->dropColumn('return_msg');
            $table->text('cut_callback_msg')->nullable();
            $table->text('sync_callback_msg')->nullable();
            $table->text('send_callback_msg')->nullable();
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
