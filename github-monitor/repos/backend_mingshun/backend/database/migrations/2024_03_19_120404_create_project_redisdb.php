<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Config;
use App\Models\Project;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Config::create([
            'key' => 'redis_db_default',
            'value' => '3',
            'description' => '默认Redis DB',
        ]);
        Config::create([
            'key' => 'redis_db_4k',
            'value' => '6',
            'description' => 'Redis DB 4K',
        ]); 
        Schema::table('projects', function (Blueprint $table) {
            $table->string('redis_db')->nullable();
        });
        $projects = Project::all();
        foreach($projects as $project){
            $project->redis_db = 3;
            $project->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_redisdb');
    }
};
