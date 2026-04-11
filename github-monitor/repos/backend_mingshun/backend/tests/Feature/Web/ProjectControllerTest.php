<?php

namespace Tests\Feature\Web;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProjectControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    // Tests: ProjectController@index | Route: GET /projects
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/projects');

        $response->assertStatus(200);
    }

    // Tests: ProjectController@store | Route: POST /projects
    public function test_store_creates_project(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->post('/projects', [
            'name' => 'TestProject',
            'enable_4k' => 1,
            'enable_photo' => 0,
            'direct_cut' => 1,
            'solo' => 0,
            'daily_cut_quota' => 20,
            'redis_db' => 3,
        ]);

        $response->assertRedirect();
    }

    // Tests: ProjectController@update | Route: PUT /projects/{id}
    public function test_update_modifies_project(): void
    {
        $admin = $this->createSuperAdmin();
        $project = Project::create(['name' => 'TestProject']);

        $response = $this->actingAs($admin)->put("/projects/{$project->id}", [
            'name' => 'UpdatedProject',
            'enable_4k' => 1,
            'enable_photo' => 0,
            'direct_cut' => 1,
            'solo' => 0,
            'daily_cut' => 0,
            'daily_cut_quota' => 20,
            'redis_db' => 3,
        ]);

        $response->assertRedirect();
    }

    // Tests: ProjectController@destroy | Route: DELETE /projects/{id}
    public function test_destroy_deletes_project(): void
    {
        $admin = $this->createSuperAdmin();
        $project = Project::create(['name' => 'TestProject']);

        $response = $this->actingAs($admin)->delete("/projects/{$project->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
