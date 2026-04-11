<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;
    use TestHelpers;

    // Tests: DashboardController@dashboard | Route: GET /dashboard
    public function test_dashboard_accessible_by_authenticated_user(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    // Tests: DashboardController@dashboard | Route: GET /dashboard
    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/');
    }
}
