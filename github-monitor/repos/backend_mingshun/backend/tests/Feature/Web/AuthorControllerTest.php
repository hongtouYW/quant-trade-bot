<?php

namespace Tests\Feature\Web;

use App\Models\Author;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AuthorControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: AuthorController@index | Route: GET /authors
    public function test_index_requires_auth(): void
    {
        $response = $this->get('/authors');

        $response->assertRedirect('/');
    }

    // Tests: AuthorController@index | Route: GET /authors
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/authors');

        $response->assertStatus(200);
    }

    // Tests: AuthorController@create | Route: GET /authors/create
    public function test_create_page_accessible(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/authors/create');

        $response->assertStatus(200);
    }

    // Tests: AuthorController@store | Route: POST /authors
    public function test_store_creates_author(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/authors', [
            'name' => 'TestAuthor',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('authors', ['name' => 'TestAuthor', 'status' => 1]);
    }

    // Tests: AuthorController@store | Route: POST /authors
    public function test_store_validates_required_fields(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/authors', []);

        $response->assertSessionHasErrors();
    }

    // Tests: AuthorController@edit | Route: GET /authors/{id}/edit
    public function test_edit_page_accessible(): void
    {
        $admin = $this->createSuperAdmin();
        $author = Author::create(['name' => 'TestAuthor', 'status' => 1]);

        $response = $this->actingAs($admin)->get("/authors/{$author->id}/edit");

        $response->assertStatus(200);
    }

    // Tests: AuthorController@update | Route: PUT /authors/{id}
    public function test_update_modifies_author(): void
    {
        $admin = $this->createSuperAdmin();
        $author = Author::create(['name' => 'TestAuthor', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/authors/{$author->id}", [
            'name' => 'UpdatedAuthor',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('authors', ['id' => $author->id, 'name' => 'UpdatedAuthor']);
    }

    // Tests: AuthorController@destroy | Route: DELETE /authors/{id}
    public function test_destroy_deletes_author(): void
    {
        $admin = $this->createSuperAdmin();
        $author = Author::create(['name' => 'TestAuthor', 'status' => 1]);

        $response = $this->actingAs($admin)->delete("/authors/{$author->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('authors', ['id' => $author->id]);
    }

    // Tests: AuthorController@changeStatus | Route: PUT /authors/changeStatus/{id}
    public function test_change_status(): void
    {
        $admin = $this->createSuperAdmin();
        $author = Author::create(['name' => 'TestAuthor', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/authors/changeStatus/{$author->id}", [
            'status' => 0,
        ]);

        $response->assertRedirect();
    }

    // Tests: AuthorController@index | Route: GET /authors
    public function test_unauthorized_role_cannot_access(): void
    {
        $uploader = $this->createUserWithRole(3);

        $response = $this->actingAs($uploader)->get('/authors');

        $this->assertTrue(in_array($response->getStatusCode(), [403, 302]));
    }
}
