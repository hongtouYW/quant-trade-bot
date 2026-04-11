<?php

namespace Tests\Feature\Web;

use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TagControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: TagController@index | Route: GET /tags
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/tags');

        $response->assertStatus(200);
    }

    // Tests: TagController@store | Route: POST /tags
    public function test_store_creates_tag(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/tags', [
            'name' => 'TestTag',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', ['name' => 'TestTag', 'status' => 1]);
    }

    // Tests: TagController@update | Route: PUT /tags/{id}
    public function test_update_modifies_tag(): void
    {
        $admin = $this->createSuperAdmin();
        $tag = Tag::create(['name' => 'TestTag', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/tags/{$tag->id}", [
            'name' => 'UpdatedTag',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'UpdatedTag']);
    }

    // Tests: TagController@destroy | Route: DELETE /tags/{id}
    public function test_destroy_deletes_tag(): void
    {
        $admin = $this->createSuperAdmin();
        $tag = Tag::create(['name' => 'TestTag', 'status' => 1]);

        $response = $this->actingAs($admin)->delete("/tags/{$tag->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    // Tests: TagController@changeStatus | Route: PUT /tags/changeStatus/{id}
    public function test_change_status(): void
    {
        $admin = $this->createSuperAdmin();
        $tag = Tag::create(['name' => 'TestTag', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/tags/changeStatus/{$tag->id}", [
            'status' => 0,
        ]);

        $response->assertRedirect();
    }
}
