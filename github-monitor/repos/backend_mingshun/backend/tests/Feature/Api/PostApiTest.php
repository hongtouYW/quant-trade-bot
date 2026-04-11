<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PostApiTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.scrap_public_key' => 'test_public_key']);
        config(['app.scrap_private_key' => 'test_private_key']);
    }

    // Tests: PostApiController@post | Route: POST /api/post
    public function test_post_endpoint_requires_auth(): void
    {
        $response = $this->postJson('/api/post');

        $response->assertStatus(401);
    }

    // Tests: PostApiController@post | Route: POST /api/post
    public function test_post_endpoint_with_auth(): void
    {
        $params = $this->getApiAuthParams();

        $response = $this->postJson('/api/post', $params);

        $this->assertNotEquals(401, $response->getStatusCode());
    }
}
