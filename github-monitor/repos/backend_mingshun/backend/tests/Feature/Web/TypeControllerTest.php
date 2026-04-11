<?php

namespace Tests\Feature\Web;

use App\Models\Type;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TypeControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: TypeController@index | Route: GET /types
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/types');

        $response->assertStatus(200);
    }

    // Tests: TypeController@store | Route: POST /types
    public function test_store_creates_type(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/types', [
            'name' => 'TestType',
            'status' => 1,
            'assigned_order' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', ['name' => 'TestType', 'status' => 1]);
    }

    // Tests: TypeController@update | Route: PUT /types/{id}
    public function test_update_modifies_type(): void
    {
        $admin = $this->createSuperAdmin();
        $type = Type::create(['name' => 'TestType', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/types/{$type->id}", [
            'name' => 'UpdatedType',
            'status' => 1,
            'assigned_order' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('types', ['id' => $type->id, 'name' => 'UpdatedType']);
    }

    // Tests: TypeController@destroy | Route: DELETE /types/{id}
    public function test_destroy_deletes_type(): void
    {
        $admin = $this->createSuperAdmin();
        $type = Type::create(['name' => 'TestType', 'status' => 1]);

        $response = $this->actingAs($admin)->delete("/types/{$type->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('types', ['id' => $type->id]);
    }

    // Tests: TypeController@changeStatus | Route: PUT /types/changeStatus/{id}
    public function test_change_status(): void
    {
        $admin = $this->createSuperAdmin();
        $type = Type::create(['name' => 'TestType', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/types/changeStatus/{$type->id}", [
            'status' => 0,
        ]);

        $response->assertRedirect();
    }
}
