<?php

namespace Tests\Feature\Web;

use App\Models\Config;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ConfigControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: ConfigController@index | Route: GET /configs
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/configs');

        $response->assertStatus(200);
    }

    // Tests: ConfigController@update | Route: PUT /configs/{id}
    public function test_update_modifies_config(): void
    {
        $admin = $this->createSuperAdmin();
        $config = Config::create([
            'key' => 'test_key',
            'value' => 'test_val',
            'description' => 'Test',
        ]);

        $response = $this->actingAs($admin)->put("/configs/{$config->id}", [
            'key' => 'test_key',
            'value' => 'updated_val',
            'description' => 'Test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('configs', ['id' => $config->id, 'value' => 'updated_val']);
    }
}
