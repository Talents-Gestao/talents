<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\SyncAdminUserPermissions;
use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class DeleteCompanySurveyTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_super_admin_can_delete_survey_with_archive(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fx = $this->createSurveyFixture();

        $this->actingAs($admin)
            ->delete(route('admin.companies.surveys.destroy', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect(route('admin.companies.show', $fx->company))
            ->assertSessionHas('success');

        $trashed = Survey::withTrashed()->find($fx->survey->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertSame($admin->id, $trashed->deleted_by);
        $this->assertNotNull($trashed->archive_path);
        Storage::disk('local')->assertExists($trashed->archive_path);

        $payload = json_decode(Storage::disk('local')->get($trashed->archive_path), true);
        $this->assertSame($fx->survey->id, $payload['survey']['id']);
        $this->assertSame($admin->id, $payload['deleted_by']['id']);
    }

    public function test_deleted_survey_is_hidden_from_client(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fx = $this->createSurveyFixture();
        $client = User::factory()->companyAdmin($fx->company->id)->create();

        $this->actingAs($admin)
            ->delete(route('admin.companies.surveys.destroy', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect();

        $this->actingAs($client)
            ->get(route('client.surveys.show', $fx->survey))
            ->assertNotFound();

        $this->actingAs($client)
            ->get(route('client.surveys.results', $fx->survey))
            ->assertNotFound();
    }

    public function test_deleted_survey_public_link_returns_not_found(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fx = $this->createSurveyFixture();

        $this->actingAs($admin)
            ->delete(route('admin.companies.surveys.destroy', [$fx->company->id, $fx->survey->id]))
            ->assertRedirect();

        $this->get(route('survey.public', $fx->survey->public_token))
            ->assertNotFound();
    }

    public function test_admin_without_delete_permission_is_forbidden(): void
    {
        $fx = $this->createSurveyFixture();
        $collaborator = User::factory()->superAdmin()->create(['is_owner' => false]);
        $workspace = $collaborator->talentsWorkspace();
        $this->assertNotNull($workspace);

        app(SyncAdminUserPermissions::class)->execute($workspace, [
            [
                'module' => AdminPermissionModule::Companies->value,
                'action' => PermissionAction::View->value,
            ],
        ]);

        $this->actingAs($collaborator)
            ->delete(route('admin.companies.surveys.destroy', [$fx->company->id, $fx->survey->id]))
            ->assertForbidden();

        $this->assertNull(Survey::withTrashed()->find($fx->survey->id)?->deleted_at);
    }

    public function test_cannot_delete_survey_from_other_company(): void
    {
        $admin = User::factory()->superAdmin()->create(['is_owner' => true]);
        $fxA = $this->createSurveyFixture();
        $fxB = $this->createSurveyFixture();

        $this->actingAs($admin)
            ->delete(route('admin.companies.surveys.destroy', [$fxA->company->id, $fxB->survey->id]))
            ->assertNotFound();

        $this->assertNull(Survey::withTrashed()->find($fxB->survey->id)?->deleted_at);
    }
}
