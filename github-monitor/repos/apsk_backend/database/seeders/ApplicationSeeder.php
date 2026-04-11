<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
[
                'application_name' => 'Android 2.25.1',
                'platform' => 'Android',
                'version' => '2.25.1',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Android 8.9.42',
                'platform' => 'Android',
                'version' => '8.9.42',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Android 2.8.7',
                'platform' => 'Android',
                'version' => '2.8.7',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Android 7.65.0',
                'platform' => 'Android',
                'version' => '7.65.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Android 9.10.5',
                'platform' => 'Android',
                'version' => '9.10.5',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iOS 2.3.19',
                'platform' => 'iOS',
                'version' => '2.3.19',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iOS 2.82.1',
                'platform' => 'iOS',
                'version' => '2.82.1',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iOS 8.75.0',
                'platform' => 'iOS',
                'version' => '8.75.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iOS 3.0.2',
                'platform' => 'iOS',
                'version' => '3.0.2',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iOS 7.02.5',
                'platform' => 'iOS',
                'version' => '7.02.5',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'HarmonyOS 3.1.0',
                'platform' => 'HarmonyOS',
                'version' => '3.1.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'WearOS 2.60.0',
                'platform' => 'Wear OS',
                'version' => '2.60.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Windows 10.0.1',
                'platform' => 'Windows',
                'version' => '10.0.1',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'macOS 1.5.0',
                'platform' => 'macOS',
                'version' => '1.5.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'watchOS 9.2.0',
                'platform' => 'watchOS',
                'version' => '9.2.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'iPadOS 3.4.0',
                'platform' => 'iPadOS',
                'version' => '3.4.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'tvOS 4.1.0',
                'platform' => 'tvOS',
                'version' => '4.1.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Tizen 6.0.0',
                'platform' => 'Tizen',
                'version' => '6.0.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'KaiOS 3.2.0',
                'platform' => 'KaiOS',
                'version' => '3.2.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Linux 2.1.0',
                'platform' => 'Linux',
                'version' => '2.1.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'Web 1.0.0',
                'platform' => 'Web',
                'version' => '1.0.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'FireOS 8.3.0',
                'platform' => 'Fire OS',
                'version' => '8.3.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
            [
                'application_name' => 'RokuOS 12.0.0',
                'platform' => 'Roku OS',
                'version' => '12.0.0',
                'status' => 1,
                'delete' => 0,
                'created_on' => '2025-05-24 13:25:00',
                'updated_on' => '2025-05-24 13:25:00',
            ],
        ];

        foreach ($applications as $app) {
            DB::table('tbl_application')->insert($app);
        }
    }
}
