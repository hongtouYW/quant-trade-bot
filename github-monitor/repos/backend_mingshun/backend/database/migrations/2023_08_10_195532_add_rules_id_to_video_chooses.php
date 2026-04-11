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
            $table->text('project_rules')->nullable();
            $table->unsignedInteger('project_rule_id')->nullable();
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
