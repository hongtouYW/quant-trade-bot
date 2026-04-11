<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ResetSupervisorPassword extends Command
{
    protected $signature = 'user:reset-supervisor-password';

    protected $description = 'Reset password for users with role 2 (classification supervisor)';

    public function handle()
    {
        // $users = User::whereHas('role', function ($q) {
        //     $q->where('roles.id', 6);
        // })->get();

        // if ($users->isEmpty()) {
        //     $this->info('No users found with role 2.');
        //     return 0;
        // }

        // $this->info("Found {$users->count()} user(s) with role 2.");

        // $data = [];
        // foreach ($users as $user) {
        //     $plainPassword = Str::random(12);

        //     // Manually set both plain and hashed password
        //     $user->plain_password = $plainPassword;
        //     $user->password = $plainPassword;
        //     $user->save();

        //     $data[] = [
        //         'username' => $user->username,
        //         'password' => $plainPassword,
        //     ];

        //     $this->info("User: {$user->username} - New password: {$plainPassword}");
        // }

        // $filename = 'project_supervisor_passwords_' . date('Y-m-d_His') . '.csv';
        // $storagePath = storage_path('app/public/' . $filename);

        // // Ensure directory exists
        // File::ensureDirectoryExists(storage_path('app/public'));

        // $handle = fopen($storagePath, 'w');
        // fputcsv($handle, ['username', 'password']);
        // foreach ($data as $row) {
        //     fputcsv($handle, $row);
        // }
        // fclose($handle);

        // $downloadUrl = asset('storage/' . $filename);

        // $this->info('Password reset completed.');
        // $this->info('CSV exported: ' . $storagePath);
        // $this->info('Download link: ' . $downloadUrl);

        // return 0;
    }
}
