<?php

use App\Models\Video;
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
        Video::whereHas('types', function ($q){
            $q->where("types.id", Video::SHOWCODEOTHERS);
        })->whereNull('author_id')->update(['author_id'=>22483]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
