<?php

use App\Models\VideoChoose;
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
        $videoChooses = VideoChoose::where('status',5)->whereDate('cut_at', '<>', now()->toDateString())->get();
        foreach($videoChooses as $videoChoose){
            $videoChoose->status = 8;
            $videoChoose->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
