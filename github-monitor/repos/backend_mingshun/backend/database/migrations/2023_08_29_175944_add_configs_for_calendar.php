<?php

use App\Models\Config;
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
        $config = Config::where('id',1)->first();
        if($config){
            $config->delete();
        }
        Config::create([
            'key' => 'calendar_show_limit',
            'value' => 4,
            'description' => '超级管理员在日历显示多少个审核者',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
