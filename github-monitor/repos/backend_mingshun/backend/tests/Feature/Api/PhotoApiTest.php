<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PhotoApiTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.scrap_public_key' => 'test_public_key']);
        config(['app.scrap_private_key' => 'test_private_key']);
    }

    // Tests: PhotoApiController@photo | Route: POST /api/photo
    public function test_photo_endpoint_accessible(): void
    {
        $params = $this->getApiAuthParams();

        $response = $this->postJson('/api/photo', $params);

        $this->assertNotEquals(401, $response->getStatusCode());
    }
}
