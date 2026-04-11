<?php

use App\Models\User;
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
        DB::table('roles')->insert([
            [
                'id' => 999,
                'name' => '统计',
            ],
        ]);
        $user = User::create([
            'username' => 'telegram_graph',
            'password' => bcrypt('123456'),
            'role' => 999,
            'type' => 3
        ]);
        DB::table('user_roles')->insert([
            [
                'user_id' => $user->id,
                'role_id' => 999,
            ],
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
