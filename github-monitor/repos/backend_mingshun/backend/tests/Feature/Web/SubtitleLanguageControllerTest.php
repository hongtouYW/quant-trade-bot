<?php

namespace Tests\Feature\Web;

use App\Models\SubtitleLanguage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SubtitleLanguageControllerTest extends TestCase
{
    use DatabaseTransactions, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        if (!\Schema::hasTable('subtitle_languages')) {
            $this->markTestSkipped('subtitle_languages table does not exist');
        }
    }

    // Tests: SubtitleLanguageController@index | Route: GET /subtitleLanguages
    public function test_index_accessible_by_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get('/subtitleLanguages');

        $response->assertStatus(200);
    }

    // Tests: SubtitleLanguageController@store | Route: POST /subtitleLanguages
    public function test_store_creates_subtitle_language(): void
    {
        $admin = $this->createSuperAdmin();

        $label = 'test_' . uniqid();
        $response = $this->actingAs($admin)->post('/subtitleLanguages', [
            'label' => $label,
            'name' => 'TestLang',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subtitle_languages', ['label' => $label, 'name' => 'TestLang', 'status' => 1]);
    }

    // Tests: SubtitleLanguageController@update | Route: PUT /subtitleLanguages/{id}
    public function test_update_modifies_subtitle_language(): void
    {
        $admin = $this->createSuperAdmin();
        $label = 'test_' . uniqid();
        $subtitleLanguage = SubtitleLanguage::create(['label' => $label, 'name' => 'English', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/subtitleLanguages/{$subtitleLanguage->id}", [
            'label' => $label,
            'name' => 'UpdatedEnglish',
            'status' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('subtitle_languages', ['id' => $subtitleLanguage->id, 'name' => 'UpdatedEnglish']);
    }

    // Tests: SubtitleLanguageController@destroy | Route: DELETE /subtitleLanguages/{id}
    public function test_destroy_deletes_subtitle_language(): void
    {
        $admin = $this->createSuperAdmin();
        $subtitleLanguage = SubtitleLanguage::create(['label' => 'test_' . uniqid(), 'name' => 'English', 'status' => 1]);

        $response = $this->actingAs($admin)->delete("/subtitleLanguages/{$subtitleLanguage->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('subtitle_languages', ['id' => $subtitleLanguage->id]);
    }

    // Tests: SubtitleLanguageController@changeStatus | Route: PUT /subtitleLanguages/changeStatus/{id}
    public function test_change_status(): void
    {
        $admin = $this->createSuperAdmin();
        $subtitleLanguage = SubtitleLanguage::create(['label' => 'test_' . uniqid(), 'name' => 'English', 'status' => 1]);

        $response = $this->actingAs($admin)->put("/subtitleLanguages/changeStatus/{$subtitleLanguage->id}", [
            'status' => 0,
        ]);

        $response->assertRedirect();
    }
}
