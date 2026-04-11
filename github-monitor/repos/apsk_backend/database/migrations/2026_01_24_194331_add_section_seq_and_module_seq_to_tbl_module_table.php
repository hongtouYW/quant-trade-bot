<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_module', function (Blueprint $table) {
            $table->integer('section_seq')
                ->default(0)
                ->after('section');

            $table->integer('module_seq')
                ->default(0)
                ->after('section_seq');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_module', function (Blueprint $table) {
            $table->dropColumn(['section_seq', 'module_seq']);
        });
    }
};
