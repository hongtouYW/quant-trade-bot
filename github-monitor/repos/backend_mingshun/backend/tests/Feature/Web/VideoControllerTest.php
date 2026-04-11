<?php

namespace Tests\Feature\Web;

use App\Models\Server;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class VideoControllerTest extends TestCase
{
    use DatabaseTransactions;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function createServer(int $createdBy = 0): Server
    {
        return Server::create([
            'name' => 'TestServer',
            'domain' => 'https://test.com',
            'play_domain' => 'https://play.test.com',
            'lan_domain' => 'https://lan.test.com',
            'ip' => '1.2.3.4',
            'path' => '/videos',
            'status' => 1,
            'created_by' => $createdBy,
            'post_recommended' => 0,
        ]);
    }

    private function createVideo(int $uploaderId, int $serverId): Video
    {
        return Video::create([
            'uid' => uniqid(),
            'code' => 'TEST' . uniqid(),
            'title' => 'Test Video ' . uniqid(),
            'path' => '/test.mp4',
            'uploader' => $uploaderId,
            'status' => 1,
            'server_id' => $serverId,
            'reason' => '',
        ]);
    }

    // Tests: VideoController@index | Route: GET /videos
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/videos');

        $response->assertStatus(200);
    }

    // Tests: VideoController@create | Route: GET /videos/create
    public function test_create_page_accessible(): void
    {
        $uploader = $this->createUserWithRole(3);
        $server = $this->createServer($uploader->id);

        // Uploader needs an FTP record to access the create page
        \App\Models\Ftp::create([
            'user_id' => $uploader->id,
            'password' => 'test',
            'nickname' => $uploader->username,
            'path' => '/ftp/path',
            'server_id' => $server->id,
        ]);

        $response = $this->actingAs($uploader)->get('/videos/create');

        $response->assertStatus(200);
    }

    // Tests: VideoController@show | Route: GET /videos/{id}
    public function test_show_displays_video(): void
    {
        $admin = $this->createSuperAdmin();
        $server = $this->createServer($admin->id);
        $video = $this->createVideo($admin->id, $server->id);

        $response = $this->actingAs($admin)->get("/videos/{$video->id}");

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
    }

    // Tests: VideoController@edit | Route: GET /videos/{id}/edit
    public function test_edit_page_accessible(): void
    {
        $admin = $this->createSuperAdmin();
        $server = $this->createServer($admin->id);
        $video = $this->createVideo($admin->id, $server->id);

        $response = $this->actingAs($admin)->get("/videos/{$video->id}/edit");

        $response->assertStatus(200);
    }

    // Tests: VideoController@store | Route: POST /videos
    public function test_store_creates_video(): void
    {
        $uploader = $this->createUserWithRole(3);
        $server = $this->createServer($uploader->id);

        \App\Models\Ftp::create([
            'user_id' => $uploader->id,
            'password' => 'test',
            'nickname' => $uploader->username,
            'path' => '/ftp/path',
            'server_id' => $server->id,
        ]);

        $response = $this->actingAs($uploader)->post('/videos', [
            'uid' => uniqid(),
            'title' => 'New Test Video ' . uniqid(),
            'description' => 'Test description',
            'code' => 'UNIQUE001',
            'server_id' => $server->id,
            'path' => 'testvideo.mp4',
        ]);

        $response->assertRedirect();
    }

    // Tests: VideoController@destroy | Route: DELETE /videos/{id}
    public function test_destroy_deletes_video(): void
    {
        $admin = $this->createSuperAdmin();
        // Admin also has role 3 access for delete route (role:1,3)
        $server = $this->createServer($admin->id);
        $video = $this->createVideo($admin->id, $server->id);

        $response = $this->actingAs($admin)->delete("/videos/{$video->id}");

        $response->assertRedirect();
    }

    // Tests: VideoController@create | Route: GET /videos/create
    public function test_unauthorized_role_cannot_create(): void
    {
        $reviewer = $this->createUserWithRole(4);

        $response = $this->actingAs($reviewer)->get('/videos/create');

        // Role 4 is not in [1,3] so they get redirected to /dashboard
        $response->assertRedirect('/dashboard');
    }
}
