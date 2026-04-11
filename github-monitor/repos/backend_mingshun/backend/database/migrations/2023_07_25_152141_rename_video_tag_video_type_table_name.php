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
        Schema::rename('video_tag', 'video_tags');
        Schema::rename('video_type', 'video_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
