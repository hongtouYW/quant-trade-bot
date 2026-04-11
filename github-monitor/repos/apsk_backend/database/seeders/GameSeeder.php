<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create gametype only if they don't exist
        $gametypes = [
            ['type_name' => 'slot','type_desc'=>'Slot Game', 'status' => 1, 'delete' => 0],
            ['type_name' => 'hot','type_desc'=>'Hot Game', 'status' => 1, 'delete' => 0],
            ['type_name' => 'fish','type_desc'=>'Fish Game', 'status' => 1, 'delete' => 0],
            ['type_name' => 'sport','type_desc'=>'Sport Game', 'status' => 1, 'delete' => 0],
            ['type_name' => 'live','type_desc'=>'Live Game', 'status' => 1, 'delete' => 0],
        ];

        foreach ($gametypes as $gametype) {
            DB::table('tbl_gametype')->insert([
                'type_name' => $gametype['type_name'],
                'type_desc' => $gametype['type_desc'],
                'status' => $gametype['status'],
                'delete' => $gametype['delete'],
                'created_on' => now(),
                'updated_on' => now(),
            ]);
        }

    }
}
