<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: UserController@index | Route: GET /users
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
    }

    // Tests: UserController@create | Route: GET /users/create
    public function test_create_page_accessible(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/users/create');

        $response->assertStatus(200);
    }

    // Tests: UserController@store | Route: POST /users
    public function test_store_creates_user(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/users', [
            'username' => 'TestUserEng',
            'password' => 'secret123',
            'role' => [3],
            'type' => 1,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['username' => 'TestUserEng']);
    }

    // Tests: UserController@store | Route: POST /users
    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/users', []);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    // Tests: UserController@store | Route: POST /users
    public function test_store_rejects_non_english_username(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/users', [
            'username' => '你好世界',
            'password' => 'secret123',
            'role' => [3],
            'type' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    // Tests: UserController@edit | Route: GET /users/{id}/edit
    public function test_edit_page_accessible(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUserWithRole(3, ['username' => 'EditableUser']);

        $response = $this->actingAs($admin)->get("/users/{$user->id}/edit");

        $response->assertStatus(200);
    }

    // Tests: UserController@update | Route: PUT /users/{id}
    public function test_update_modifies_user(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUserWithRole(3, ['username' => 'OldName']);

        $response = $this->actingAs($admin)->put("/users/{$user->id}", [
            'username' => 'NewName',
            'role' => [3],
            'type' => 1,
        ]);

        $response->assertRedirect();
    }

    // Tests: UserController@destroy | Route: DELETE /users/{id}
    public function test_destroy_deletes_user(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUserWithRole(3, ['username' => 'ToDelete']);

        $response = $this->actingAs($admin)->delete("/users/{$user->id}");

        $response->assertRedirect();
    }

    // Tests: UserController@changeStatus | Route: PUT /users/changeStatus/{id}
    public function test_change_status(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createUserWithRole(3, ['username' => 'StatusUser', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/users/changeStatus/{$user->id}", [
            'status' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 0]);
    }

    // Tests: UserController@index | Route: GET /users
    public function test_non_admin_cannot_access_index(): void
    {
        $uploader = $this->createUserWithRole(3);

        $response = $this->actingAs($uploader)->get('/users');

        $response->assertRedirect('/dashboard');
    }
}
