<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Temporarily disable FK checks
        DB::table('tbl_version')->insert([
            'platform' => "ios",
            'version' => "1.0",
            'latest_version' => "1.0",
            'minimun_version' => "1.0",
            'force_update' => 0,
            'created_on' => '2025-05-01 00:00:00',
            'updated_on' => '2025-05-01 00:00:00',
        ]);
        DB::table('tbl_version')->insert([
            'platform' => "android",
            'version' => "1.0",
            'latest_version' => "1.0",
            'minimun_version' => "1.0",
            'force_update' => 0,
            'created_on' => '2025-05-01 00:00:00',
            'updated_on' => '2025-05-01 00:00:00',
        ]);

    }
}
