<?php

namespace Tests\Feature\Web;

use App\Models\CutServer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CutServerControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: CutServerController@index | Route: GET /cutServer
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/cutServer');

        $response->assertStatus(200);
    }

    // Tests: CutServerController@store | Route: POST /cutServer
    public function test_store_creates_cut_server(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/cutServer', [
            'ip' => '10.0.0.1',
            'redis_port' => '6379',
            'redis_db' => '0',
            'redis_password' => 'secret',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cut_servers', ['ip' => '10.0.0.1', 'redis_port' => '6379']);
    }

    // Tests: CutServerController@update | Route: PUT /cutServer/{id}
    public function test_update_modifies_cut_server(): void
    {
        $admin = $this->createSuperAdmin();
        $cutServer = CutServer::create([
            'ip' => '10.0.0.1',
            'redis_port' => '6379',
            'redis_db' => '0',
            'redis_password' => 'secret',
        ]);

        $response = $this->actingAs($admin)->put("/cutServer/{$cutServer->id}", [
            'ip' => '10.0.0.2',
            'redis_port' => '6379',
            'redis_db' => '0',
            'redis_password' => 'secret',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cut_servers', ['id' => $cutServer->id, 'ip' => '10.0.0.2']);
    }

    // Tests: CutServerController@destroy | Route: DELETE /cutServer/{id}
    public function test_destroy_deletes_cut_server(): void
    {
        $admin = $this->createSuperAdmin();
        $cutServer = CutServer::create([
            'ip' => '10.0.0.1',
            'redis_port' => '6379',
            'redis_db' => '0',
            'redis_password' => 'secret',
        ]);

        $response = $this->actingAs($admin)->delete("/cutServer/{$cutServer->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('cut_servers', ['id' => $cutServer->id]);
    }
}
