<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = Carbon::now();
        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => '超级管理员',
            ],
            [
                'id' => 2,
                'name' => '分类主管',
            ],
            [
                'id' => 3,
                'name' => '上传手',
            ],
            [
                'id' => 4,
                'name' => '审核手',
            ],
            [
                'id' => 5,
                'name' => '项目运营',
            ],
            [
                'id' => 6,
                'name' => '项目主管',
            ],
        ]);

        DB::table('users')->insert([
            [
                'id' => 1,
                'username' => 'superadmin',
                'password' => bcrypt('password'),
                'created_at' => $now,
            ],
            [
                'id' => 2,
                'username' => 'supervisor',
                'password' => bcrypt('123456'),
                'created_at' => $now,
            ],
            [
                'id' => 3,
                'username' => 'uploader',
                'password' => bcrypt('123456'),
                'created_at' => $now,
            ],
            [
                'id' => 4,
                'username' => 'reviewer',
                'password' => bcrypt('123456'),
                'created_at' => $now,
            ],
            [
                'id' => 5,
                'username' => 'operator',
                'password' => bcrypt('123456'),
                'created_at' => $now,
            ],
            [
                'id' => 6,
                'username' => 'project_sv',
                'password' => bcrypt('123456'),
                'created_at' => $now,
            ],
        ]);

        DB::table('user_roles')->insert([
            [
                'user_id' => 1,
                'role_id' => 1,
            ],
            [
                'user_id' => 2,
                'role_id' => 2,
            ],
            [
                'user_id' => 3,
                'role_id' => 3,
            ],
            [
                'user_id' => 4,
                'role_id' => 4,
            ],
            [
                'user_id' => 5,
                'role_id' => 5,
            ],
            [
                'user_id' => 6,
                'role_id' => 6,
            ],
        ]);
    }
}
