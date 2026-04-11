<?php

namespace Tests\Feature\Web;

use App\Models\Server;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ServerControllerTest extends TestCase
{
    use DatabaseTransactions;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: ServerController@index | Route: GET /servers
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/servers');

        $response->assertStatus(200);
    }

    // Tests: ServerController@store | Route: POST /servers
    public function test_store_creates_server(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/servers', [
            'name' => 'TestServer',
            'domain' => 'https://test.com',
            'play_domain' => 'https://play.test.com',
            'ip' => '1.2.3.4',
            'path' => '/videos',
            'status' => 1,
            'post_recommended' => 0,
        ]);

        $response->assertRedirect(route('servers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('servers', ['name' => 'TestServer', 'ip' => '1.2.3.4']);
    }

    // Tests: ServerController@update | Route: PUT /servers/{id}
    public function test_update_modifies_server(): void
    {
        $admin = $this->createSuperAdmin();
        $server = Server::create([
            'name' => 'TestServer',
            'domain' => 'https://test.com',
            'play_domain' => 'https://play.test.com',
            'ip' => '1.2.3.4',
            'path' => '/videos',
            'status' => 1,
            'created_by' => $admin->id,
            'post_recommended' => 0,
        ]);

        $response = $this->actingAs($admin)->put("/servers/{$server->id}", [
            'name' => 'UpdatedServer',
            'domain' => 'https://updated.com',
            'play_domain' => 'https://play.updated.com',
            'ip' => '1.2.3.4',
            'path' => '/videos',
            'status' => 1,
            'post_recommended' => 0,
        ]);

        $response->assertRedirect(route('servers.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('servers', ['name' => 'UpdatedServer']);
    }

    // Tests: ServerController@destroy | Route: DELETE /servers/{id}
    public function test_destroy_deletes_server(): void
    {
        $admin = $this->createSuperAdmin();
        $server = Server::create([
            'name' => 'TestServer',
            'domain' => 'https://test.com',
            'play_domain' => 'https://play.test.com',
            'ip' => '5.6.7.8',
            'path' => '/videos',
            'status' => 1,
            'created_by' => $admin->id,
            'post_recommended' => 0,
        ]);

        $response = $this->actingAs($admin)->delete("/servers/{$server->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }
}
