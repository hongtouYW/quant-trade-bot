<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Roles;
use App\Models\Module;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Temporarily disable FK checks
        DB::statement('DELETE FROM `tbl_module` WHERE 1');
        DB::statement('ALTER TABLE `tbl_module` AUTO_INCREMENT = 1');
        DB::statement('DELETE FROM `tbl_access` WHERE 1');
        DB::statement('ALTER TABLE `tbl_access` AUTO_INCREMENT = 1');
        $addmodules = [
            ['module_name' => 'admin_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'user'],
            ['module_name' => 'member_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'member'],
            ['module_name' => 'manager_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'manager'],
            ['module_name' => 'shop_management','module_desc'=> null, 'section'=>'admin', 'controller'=> 'shop'],
            ['module_name' => 'country_management','module_desc'=> null, 'section'=>'area',  'controller'=> 'country'],
            ['module_name' => 'state_management','module_desc'=> null, 'section'=>'area',  'controller'=> 'state'],
            ['module_name' => 'area_management','module_desc'=> null, 'section'=>'area', 'controller'=> 'area'],
            ['module_name' => 'role_management','module_desc'=> null, 'section'=> 'permission', 'controller'=> 'role'],
            ['module_name' => 'access_management','module_desc'=> null, 'section'=>'permission', 'controller'=> 'access'],
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
            ['module_name' => 'feedback_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'feedback'],
            ['module_name' => 'question_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'question'],
            ['module_name' => 'notification_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'notification'],
            ['module_name' => 'log_management','module_desc'=> null, 'section'=>'message', 'controller'=> 'log'],
            ['module_name' => 'slider_management','module_desc'=> null, 'section'=>'slider', 'controller'=> 'slider'],
            ['module_name' => 'application_management','module_desc'=> null, 'section'=>'application', 'controller'=> 'application'],
            ['module_name' => 'promotion_management','module_desc'=> null, 'section'=>'marketing', 'controller'=> 'promotion'],
            ['module_name' => 'performance_management','module_desc'=> null, 'section'=>'marketing', 'controller'=> 'promotion'],
        ];
        foreach ($addmodules as $module) {
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

        
        $modules = Module::where('status', 1)
              ->where('delete', 0)
              ->orderBy('module_name')
              ->get();
        foreach ($modules as $key => $module) {
            $superadmin = [
                'user_role' => 'superadmin',
                'module_id' => $module->module_id,
                'can_view'=>true,
                'can_edit'=>true,
                'can_delete'=>true,
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-01 00:00:00',
                'updated_on' => '2025-05-01 00:00:00',
            ];
            DB::table('tbl_access')->insert($superadmin);
        }
        foreach ($modules as $key => $module) {
            if ( in_array( $module->module_name, ['admin_management']) ) {
                continue;
            }
            $admin = [
                'user_role' => 'admin',
                'module_id' => $module->module_id,
                'can_view'=>true,
                'can_edit'=>true,
                'can_delete'=>true,
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-01 00:00:00',
                'updated_on' => '2025-05-01 00:00:00',
            ];
            DB::table('tbl_access')->insert($admin);
        }



    }
}
