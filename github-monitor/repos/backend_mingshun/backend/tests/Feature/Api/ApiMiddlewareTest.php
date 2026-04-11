<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ApiMiddlewareTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.scrap_public_key' => 'test_public_key']);
        config(['app.scrap_private_key' => 'test_private_key']);
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_without_auth_params_returns_401(): void
    {
        $response = $this->postJson('/api/video');

        $response->assertStatus(401);
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_with_wrong_public_key_returns_401(): void
    {
        $timestamp = time();
        $hash = md5('wrong_public_key' . 'test_private_key' . $timestamp);

        $response = $this->postJson('/api/video', [
            'public_key' => 'wrong_public_key',
            'timestamp' => $timestamp,
            'hash' => $hash,
        ]);

        $response->assertStatus(401);
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_with_wrong_hash_returns_401(): void
    {
        $timestamp = time();

        $response = $this->postJson('/api/video', [
            'public_key' => 'test_public_key',
            'timestamp' => $timestamp,
            'hash' => 'wrong_hash_value',
        ]);

        $response->assertStatus(401);
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_with_expired_timestamp_returns_401(): void
    {
        $timestamp = time() - 400;
        $hash = md5('test_public_key' . 'test_private_key' . $timestamp);

        $response = $this->postJson('/api/video', [
            'public_key' => 'test_public_key',
            'timestamp' => $timestamp,
            'hash' => $hash,
        ]);

        $response->assertStatus(401);
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_with_valid_auth_passes(): void
    {
        $response = $this->postJson('/api/video', $this->getApiAuthParams());

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // Tests: ApiMiddleware@handle | Route: POST /api/video
    public function test_request_with_partial_params_returns_401(): void
    {
        $response = $this->postJson('/api/video', [
            'public_key' => 'test_public_key',
        ]);

        $response->assertStatus(401);
    }
}
