<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class UserModelTest extends TestCase
{
    use DatabaseTransactions;
    use TestHelpers;

    // Tests: User::boot() saving hook
    public function test_password_is_hashed_on_save(): void
    {
        $user = User::factory()->create(['password' => 'plaintext123']);

        $this->assertNotEquals('plaintext123', $user->fresh()->getAttributes()['password']);
        $this->assertTrue(Hash::check('plaintext123', $user->fresh()->getAttributes()['password']));
    }

    // Tests: User::boot() saving hook - plain_password is stored for admin use
    public function test_plain_password_is_stored(): void
    {
        $user = User::factory()->create(['password' => 'plaintext123']);

        $attributes = $user->fresh()->getAttributes();

        $this->assertEquals('plaintext123', $attributes['plain_password']);
    }

    // Tests: User::hasRole()
    public function test_has_role_check(): void
    {
        $user = $this->createUserWithRole(1);

        $this->assertTrue($user->hasRole(1));
        $this->assertFalse($user->hasRole(2));
    }

    // Tests: User::isSuperAdmin()
    public function test_is_super_admin(): void
    {
        $user = $this->createUserWithRole(1);

        $this->assertTrue($user->isSuperAdmin());
    }

    // Tests: User::projects()
    public function test_user_has_projects_relationship(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(BelongsToMany::class, $user->projects());
    }

    // Tests: User::role()
    public function test_user_has_role_relationship(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(BelongsToMany::class, $user->role());
    }
}
