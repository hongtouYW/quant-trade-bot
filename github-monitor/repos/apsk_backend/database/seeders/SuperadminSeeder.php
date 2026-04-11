<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Roles;
use App\Models\Areas;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Temporarily disable FK checks
        // Create modules only if they don't exist
        $modules = [
            ['module_name' => 'admin_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'user'],
            ['module_name' => 'member_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'member'],
            ['module_name' => 'manager_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'manager'],
            ['module_name' => 'shop_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'shop'],

            ['module_name' => 'country_management','module_desc'=> null, 'section'=>'area',  'controller'=> 'country'],
            ['module_name' => 'state_management','module_desc'=> null, 'section'=>'area',  'controller'=> 'state'],
            ['module_name' => 'area_management','module_desc'=> null, 'section'=>'area', 'controller'=> 'area'],

            ['module_name' => 'role_management','module_desc'=> null, 'section'=> 'role', 'controller'=> 'role'],
            ['module_name' => 'permission_management','module_desc'=> null, 'section'=>'role', 'controller'=> 'permission'],

            ['module_name' => 'song_management','module_desc'=> null, 'section'=>'song', 'controller'=> 'song'],
            ['module_name' => 'genre_management','module_desc'=> null, 'section'=>'song', 'controller'=> 'genre'],
            ['module_name' => 'artist_management','module_desc'=> null, 'section'=>'song', 'controller'=> 'artist'],
            
            ['module_name' => 'vip_management','module_desc'=> null, 'section'=>'vip', 'controller'=> 'vip'],

            ['module_name' => 'bank_management','module_desc'=> null, 'section'=>'credit', 'controller'=> 'bank'],
            ['module_name' => 'payment_management','module_desc'=> null, 'section'=>'credit', 'controller'=> 'paymentgateway'],
            ['module_name' => 'credit_management','module_desc'=> null, 'section'=>'credit', 'controller'=> 'credit'],
            ['module_name' => 'shopcredit_management','module_desc'=> null, 'section'=>'credit', 'controller'=> 'shopcredit'],

            ['module_name' => 'game_management','module_desc'=> null, 'section'=>'game', 'controller'=> 'game'],
            ['module_name' => 'gamebookmark_management','module_desc'=> null, 'section'=>'game', 'controller'=> 'gamebookmark'],
            ['module_name' => 'gamemember_management','module_desc'=> null, 'section'=>'game', 'controller'=> 'gamemember'],
            ['module_name' => 'gamepoint_management','module_desc'=> null, 'section'=>'game', 'controller'=> 'gamepoint'],
            ['module_name' => 'gametype_management','module_desc'=> null, 'section'=>'game', 'controller'=> 'gametype'],

            ['module_name' => 'message_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'message'],
            ['module_name' => 'promotion_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'promotion'],
            ['module_name' => 'feedback_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'feedback'],
            ['module_name' => 'question_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'question'],
            ['module_name' => 'notification_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'notification'],
            ['module_name' => 'log_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'log'],

            ['module_name' => 'slider_management','module_desc'=> null, 'section'=>'slider', 'controller'=> 'slider'],

            ['module_name' => 'application_management','module_desc'=> null, 'section'=>'application', 'controller'=> 'application'],

        ];
        foreach ($modules as $module) {
            if (DB::table('tbl_module')->where('module_name', $module['module_name'])->exists()) {
                continue;
            }
            DB::table('tbl_module')->insert([
                'module_name' => $module['module_name'],
                'module_desc' => $module['module_desc'],
                'controller' => $module['controller'],
                'section'=> $module['section'],
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-01 00:00:00',
                'updated_on' => '2025-05-01 00:00:00',
            ]);
        }
        // Create roles only if they don't exist
        $roles = [
            [
                'role_name' => 'superadmin',
                'role_desc' => 'Super Admin',
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ],
            [
                'role_name' => 'admin',
                'role_desc' => 'Admin',
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ],
            [
                'role_name' => 'manager',
                'role_desc' => 'Manager',
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ],
            [
                'role_name' => 'shop',
                'role_desc' => 'Shop',
                'status' => 1,
                'delete' => 0,
                'created_on' => now(),
                'updated_on' => now(),
            ],
        ];
        foreach ($roles as $role) {
            if (DB::table('tbl_role')->where('role_name', $role['role_name'])->exists()) {
                continue;
            }
            Roles::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
        // Create superadmin user only if it doesn't exist
        $superadmin = [
            'user_id' => 0,
            'user_login' => '911djappsuperadmin',
            'user_pass' => Hash::make('gkAEc178hU416VadSR'),
            'user_name' => 'Super Admin',
            'user_role' => 'superadmin',
            'area_code' => 'SGP-SG-AM',
            'GAstatus' => 0,
            'status' => 1,
            'delete' => 0,
            'created_on' => '2025-05-01 00:00:00',
            'updated_on' => '2025-05-01 00:00:00',
        ];
        // DB::statement('DELETE FROM `tbl_user` WHERE 1');
        if (!DB::table('tbl_user')->where('user_login', $superadmin['user_login'])->exists()) {
            DB::table('tbl_user')->insert($superadmin);
            DB::statement('UPDATE `tbl_user` SET `user_id`="0" WHERE 1');
            DB::statement('ALTER TABLE `tbl_user` AUTO_INCREMENT = 1');
            DB::statement('DELETE FROM `personal_access_tokens` WHERE 1');
            DB::statement('ALTER TABLE `personal_access_tokens` AUTO_INCREMENT = 1');
        }
        // Create permissions only if they don't exist
        // DB::statement('DELETE FROM `tbl_permission` WHERE 1');
        // DB::statement('ALTER TABLE `tbl_permission` AUTO_INCREMENT = 1');
        // foreach ($modules as $key => $module) {
        //     $permissions = [
        //         'permission_user' => 0,
        //         'module_id' => $module->module_id,
        //         'can_view'=>true,
        //         'can_edit'=>true,
        //         'can_delete'=>true,
        //         'status' => 1,
        //         'delete' => 0,
        //         'created_on' => '2025-05-01 00:00:00',
        //         'updated_on' => '2025-05-01 00:00:00',
        //     ];
        //     DB::table('tbl_permission')->insert($permissions);
        // }
        // Default manager/shop/member increasement
        DB::statement('DELETE FROM `tbl_manager` WHERE 1');
        DB::statement('DELETE FROM `tbl_shop` WHERE 1');
        DB::statement('DELETE FROM `tbl_member` WHERE 1');
        DB::statement('ALTER TABLE `tbl_manager` AUTO_INCREMENT = 10000000');
        DB::statement('ALTER TABLE `tbl_shop`    AUTO_INCREMENT = 100000000');
        DB::statement('ALTER TABLE `tbl_member`  AUTO_INCREMENT = 1000000000');
        // // Default manager test
        // $manager = [
        //     'manager_login' => 'manager001',
        //     'manager_pass' => Hash::make('1ra0bp32hyCB8j7TTW'),
        //     'manager_name' => 'manager001',
        //     'full_name' => 'manager001',
        //     'created_on' => now(),
        //     'updated_on' => now(),
        // ];
        // DB::table('tbl_manager')->insert($manager);

    }
}
