<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AuthTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: LoginController@loginPage | Route: GET /login
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    // Tests: LoginController@login | Route: POST /login
    public function test_login_with_valid_credentials(): void
    {
        $user = $this->createSuperAdmin([
            'username' => 'testuser',
            'password' => 'password',
            'status' => 1,
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    // Tests: LoginController@login | Route: POST /login
    public function test_login_with_invalid_credentials(): void
    {
        $user = $this->createSuperAdmin([
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
    }

    // Tests: LoginController@logout | Route: GET /logout
    public function test_logout_redirects_to_login(): void
    {
        $user = $this->createSuperAdmin();

        $response = $this->actingAs($user)->get('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    // Tests: DashboardController@dashboard | Route: GET /dashboard
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/');
    }
}
