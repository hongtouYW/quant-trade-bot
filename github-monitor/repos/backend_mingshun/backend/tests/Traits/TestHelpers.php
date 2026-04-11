<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait TestHelpers
{
    protected function seedRoles(): void
    {
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => '超级管理员'],
            ['id' => 2, 'name' => '分类主管'],
            ['id' => 3, 'name' => '上传手'],
            ['id' => 4, 'name' => '审核手'],
            ['id' => 5, 'name' => '项目运营'],
            ['id' => 6, 'name' => '项目主管'],
            ['id' => 7, 'name' => '图片手'],
        ]);
    }

    protected function createUserWithRole(int $roleId, array $attributes = []): User
    {
        $this->seedRoles();
        $user = User::factory()->create($attributes);
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);
        return $user;
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        return $this->createUserWithRole(1, $attributes);
    }

    protected function getApiAuthParams(): array
    {
        $publicKey = config('app.scrap_public_key');
        $privateKey = config('app.scrap_private_key');
        $timestamp = time();
        $hash = md5($publicKey . $privateKey . $timestamp);

        return [
            'public_key' => $publicKey,
            'timestamp' => $timestamp,
            'hash' => $hash,
        ];
    }
}
