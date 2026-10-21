<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\SyncUserPermissions;
use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentValidation;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialMeasure;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Psychosocial\PgrSubsidySnapshotBuilder;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1InstrumentMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PgrSubsidyValidationPhaseNineTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return object{fx: object, admin: User, client: User}
     */
    private function seedCascadeCampaign(): object
    {
        $fx = $this->createSurveyFixture();

        foreach (['A' => 19, 'B' => 6, 'C' => 4] as $name => $size) {
            $department = Department::query()->create(['company_id' => $fx->company->id, 'name' => "Setor {$name}"]);
            for ($i = 0; $i < $size; $i++) {
                $response = SurveyResponse::query()->create([
                    'survey_id' => $fx->survey->id,
                    'session_token' => (string) Str::uuid(),
                    'department_id' => $department->id,
                    'completed_at' => now(),
                ]);
                SurveyAnswer::query()->create([
                    'survey_response_id' => $response->id,
                    'survey_template_question_id' => $fx->question->id,
                    'value' => 3,
                ]);
            }
            $this->storeResult($fx, null, $department->id, '2.500', $size);
            $this->storeResult($fx, $fx->section->id, $department->id, '2.500', $size);
        }

        $this->storeResult($fx, null, null, '2.414', 29);
        $this->storeResult($fx, $fx->section->id, null, '2.414', 29);

        return (object) [
            'fx' => $fx,
            'admin' => User::factory()->superAdmin()->create(['is_owner' => true]),
            'client' => User::factory()->companyAdmin($fx->company->id)->create(),
        ];
    }

    private function storeResult(object $fx, ?int $sectionId, ?int $departmentId, string $score, int $n): void
    {
        SurveyResult::query()->create([
            'survey_id' => $fx->survey->id,
            'survey_template_section_id' => $sectionId,
            'department_id' => $departmentId,
            'average_score' => $score,
            'risk_level' => 'yellow',
            'respondent_count' => $n,
            'meta' => ['section_title' => $sectionId ? $fx->section->title : null],
        ]);
    }

    private function releaseSubsidy(object $campaign): PsychosocialDocumentVersion
    {
        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.pgr-subsidy.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHasNoErrors();

        return PsychosocialDocumentVersion::query()->latest('id')->firstOrFail();
    }

    private function grant(User $user, PermissionModule $module, PermissionAction $action): void
    {
        UserPermission::query()->create([
            'user_workspace_id' => $user->workspaces()->firstOrFail()->id,
            'module' => $module->value,
            'action' => $action->value,
        ]);
    }

    public function test_permission_action_all_excludes_validate_and_sync_rejects_it_outside_relatorios(): void
    {
        $this->assertNotContains(PermissionAction::Validate, PermissionAction::all());
        $this->assertContains(PermissionAction::Validate, PermissionAction::forCompanyMatrix());

        $campaign = $this->seedCascadeCampaign();
        $user = User::factory()->create([
            'company_id' => $campaign->fx->company->id,
            'role' => UserRole::CompanyUser,
        ]);
        $workspace = $user->workspaces()->firstOrFail();
        $sync = app(SyncUserPermissions::class);

        try {
            $sync->execute($workspace, $campaign->fx->company, [[
                'module' => PermissionModule::Pesquisas->value,
                'action' => PermissionAction::Validate->value,
            ]]);
            $this->fail('Validate fora de Relatórios deveria ser recusado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permissions', $exception->errors());
        }

        $this->assertSame(0, UserPermission::query()->count());

        $sync->execute($workspace, $campaign->fx->company, [[
            'module' => PermissionModule::Relatorios->value,
            'action' => PermissionAction::Validate->value,
        ]]);
        $this->assertSame(1, UserPermission::query()->count());
    }

    public function test_hse_with_pending_audit_does_not_release_subsidy_or_create_document(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->survey->instrumentVersion->update([
            'instrument_code' => Nr1InstrumentMatrix::CODE_HSE_IT,
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_PENDING,
        ]);

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.pgr-subsidy.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHas('error');

        $this->assertSame(0, PsychosocialDocument::query()->count());
        $this->assertSame(0, PsychosocialDocumentVersion::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_campaign_without_pending_audit_releases_pgr_subsidy_released_pending_validation(): void
    {
        $campaign = $this->seedCascadeCampaign();
        app(PsychosocialMeasureService::class)->createEngineSuggestion($campaign->fx->survey, [
            'factor_label' => 'Exigências elevadas',
            'measure_description' => 'Revisar volume e ritmo de trabalho.',
        ]);

        $version = $this->releaseSubsidy($campaign);

        $document = PsychosocialDocument::query()->sole();
        $this->assertSame(PsychosocialDocument::TYPE_PGR_SUBSIDY, $document->type);
        $this->assertSame($version->id, $document->current_version_id);
        $this->assertSame(PsychosocialDocumentVersion::KIND_RELEASED, $version->kind);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION, $version->seal);
        $this->assertNull($version->parent_version_id);
        $this->assertNull($version->validation_block);
        $this->assertSame(PsychosocialDocumentVersion::PDF_READY, $version->pdf_status);
        Storage::disk('local')->assertExists($version->pdf_path);

        $this->assertSame(['Setor A'], array_column($version->snapshot['sectors'], 'department_name'));
        $this->assertNull($version->snapshot['eligible']['participation_label']);
        $this->assertSame('Aguardando total elegível', $version->snapshot['eligible']['hint']);
        $this->assertNull($version->snapshot['registration']['eligible_source']);
        $this->assertSame(['A aprovar'], array_column($version->snapshot['measures'], 'status_label'));
        $this->assertSame(array_fill_keys(array_keys(PgrSubsidySnapshotBuilder::INVENTORY_FIELDS), null), $version->snapshot['inventory']);

        $html = app(PsychosocialDocumentService::class)->renderHtml(PsychosocialDocument::TYPE_PGR_SUBSIDY, $version->snapshot);
        $this->assertStringContainsString('Origem dos elegíveis', $html);
        $this->assertStringContainsString('Não informado', $html);
        $this->assertStringContainsString('A aprovar', $html);
        $this->assertStringContainsString('Faixa do questionário. Não é classificação de risco ocupacional do PGR.', $html);
        $this->assertStringNotContainsString('HSE validado', $html);
        $this->assertStringNotContainsString('COPSOQ validado', $html);
        $this->assertSame(1, PsychosocialMeasure::query()->count());
    }

    public function test_user_without_validate_permission_gets_403_and_creates_no_version(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);
        $user = User::factory()->create([
            'company_id' => $campaign->fx->company->id,
            'role' => UserRole::CompanyUser,
        ]);
        $this->grant($user, PermissionModule::Relatorios, PermissionAction::View);

        $this->actingAs($user)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), ['declaration_accepted' => '1'])
            ->assertForbidden();

        $this->assertSame(1, PsychosocialDocumentVersion::query()->count());
        $this->assertSame(0, PsychosocialDocumentValidation::query()->count());
    }

    public function test_in_system_validation_creates_child_with_same_snapshot_hash_and_new_pdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $released = $this->releaseSubsidy($campaign);
        $releasedState = $released->only(['snapshot_sha256', 'content_sha256', 'pdf_path', 'pdf_sha256', 'pdf_status', 'seal', 'kind']);

        $this->actingAs($campaign->client)
            ->from(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), ['declaration_accepted' => '1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $validated = PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->sole();
        $released = $released->fresh();

        $this->assertSame($releasedState, $released->only(array_keys($releasedState)));
        $this->assertSame($released->id, $validated->parent_version_id);
        $this->assertSame($released->snapshot_sha256, $validated->snapshot_sha256);
        $this->assertSame($released->snapshot, $validated->snapshot);
        $this->assertNotSame($released->content_sha256, $validated->content_sha256);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_VALIDATED, $validated->seal);
        $this->assertSame(PsychosocialDocumentVersion::KIND_VALIDATED, $validated->kind);
        $this->assertSame(PsychosocialDocumentVersion::PDF_READY, $validated->pdf_status);
        $this->assertNotSame($released->pdf_path, $validated->pdf_path);
        Storage::disk('local')->assertExists($released->pdf_path);
        Storage::disk('local')->assertExists($validated->pdf_path);
        $this->assertSame($validated->id, PsychosocialDocument::query()->sole()->current_version_id);

        $row = PsychosocialDocumentValidation::query()->sole();
        $this->assertSame(PsychosocialDocumentValidation::PATH_IN_SYSTEM, $row->path);
        $this->assertSame($campaign->client->id, $row->user_id);
        $this->assertTrue($row->declaration_accepted);
        $this->assertSame(PsychosocialDocumentService::IN_SYSTEM_DECLARATION, $row->declaration_text);
    }

    public function test_validating_the_same_released_again_is_refused(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);
        $url = route('client.surveys.psychosocial-documents.validate', [
            $campaign->fx->survey->id,
            PsychosocialDocument::TYPE_PGR_SUBSIDY,
        ]);

        $this->actingAs($campaign->client)->post($url, ['declaration_accepted' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($campaign->client)->from(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->post($url, ['declaration_accepted' => '1'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->count());
        $this->assertSame(1, PsychosocialDocumentValidation::query()->count());
    }

    public function test_informed_validation_without_proof_creates_nothing(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);

        $this->actingAs($campaign->admin)
            ->from(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->post(route('admin.companies.surveys.psychosocial-documents.validate-informed', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), [
                'name' => 'Maria SST',
                'role' => 'Técnica de segurança',
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('proof');

        $this->assertSame(1, PsychosocialDocumentVersion::query()->count());
        $this->assertSame(0, PsychosocialDocumentValidation::query()->count());
    }

    public function test_informed_validation_with_proof_creates_child_and_row_without_changing_company(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->company->update(['legal_name' => 'Razão Original Ltda']);
        $released = $this->releaseSubsidy($campaign);
        $proof = UploadedFile::fake()->create('comprovante.pdf', 40, 'application/pdf');

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.psychosocial-documents.validate-informed', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), [
                'name' => 'Maria SST',
                'role' => 'Técnica de segurança',
                'registry' => '',
                'date' => now()->toDateString(),
                'proof' => $proof,
            ])
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHasNoErrors();

        $validated = PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->sole();
        $this->assertSame($released->snapshot_sha256, $validated->snapshot_sha256);
        $this->assertNotSame($released->content_sha256, $validated->content_sha256);

        $row = PsychosocialDocumentValidation::query()->sole();
        $this->assertSame(PsychosocialDocumentValidation::PATH_INFORMED, $row->path);
        $this->assertSame('Maria SST', $row->informed_name);
        $this->assertSame('Técnica de segurança', $row->informed_role);
        $this->assertNull($row->informed_registry);
        $this->assertSame($campaign->admin->id, $row->recorded_by);
        $this->assertNotNull($row->proof_file_path);
        Storage::disk('local')->assertExists($row->proof_file_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($row->proof_file_path)), $row->proof_sha256);

        $company = $campaign->fx->company->fresh();
        $this->assertSame('Razão Original Ltda', $company->legal_name);
        $this->assertSame('11.111.111/0001-11', $company->cnpj);
    }

    public function test_recalculating_does_not_change_hashes_or_files(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $released = $this->releaseSubsidy($campaign);
        $this->actingAs($campaign->client)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), ['declaration_accepted' => '1']);
        $validated = PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->sole();

        $before = [
            $released->fresh()->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']),
            $validated->fresh()->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']),
        ];
        $releasedBytes = Storage::disk('local')->get($released->pdf_path);
        $validatedBytes = Storage::disk('local')->get($validated->pdf_path);

        app(SurveyResultCalculator::class)->recalculate($campaign->fx->survey->fresh());

        $this->assertSame($before[0], $released->fresh()->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']));
        $this->assertSame($before[1], $validated->fresh()->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']));
        $this->assertSame($releasedBytes, Storage::disk('local')->get($released->pdf_path));
        $this->assertSame($validatedBytes, Storage::disk('local')->get($validated->pdf_path));
    }

    public function test_no_evidence_dossier_is_created(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);
        $this->actingAs($campaign->client)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), ['declaration_accepted' => '1']);

        $this->assertSame(0, PsychosocialDocument::query()->where('type', PsychosocialDocument::TYPE_EVIDENCE_DOSSIER)->count());
        $this->assertSame([PsychosocialDocument::TYPE_PGR_SUBSIDY], PsychosocialDocument::query()->pluck('type')->all());
    }

    public function test_download_of_validated_version_does_not_call_dompdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);
        $this->actingAs($campaign->client)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]), ['declaration_accepted' => '1']);
        $validated = PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->sole();

        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        $response = $this->actingAs($campaign->client)
            ->get(route('client.surveys.psychosocial-documents.download', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]))
            ->assertOk();
        $this->assertSame(Storage::disk('local')->get($validated->pdf_path), $response->streamedContent());

        $log = PsychosocialAccessLog::query()->where('resource', 'document_download')->sole();
        $this->assertSame($validated->id, $log->document_version_id);
    }

    public function test_hse_executive_brief_can_still_be_validated(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->survey->instrumentVersion->update([
            'instrument_code' => Nr1InstrumentMatrix::CODE_HSE_IT,
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_PENDING,
        ]);

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHasNoErrors();

        $this->actingAs($campaign->client)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_EXECUTIVE_BRIEF,
            ]), ['declaration_accepted' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PsychosocialDocument::query()->where('type', PsychosocialDocument::TYPE_EXECUTIVE_BRIEF)->count());
        $this->assertSame(0, PsychosocialDocument::query()->where('type', PsychosocialDocument::TYPE_PGR_SUBSIDY)->count());
        $this->assertSame(1, PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->count());
    }

    public function test_in_system_validation_without_declaration_creates_nothing(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->releaseSubsidy($campaign);

        $this->actingAs($campaign->client)
            ->from(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_PGR_SUBSIDY,
            ]))
            ->assertSessionHasErrors('declaration_accepted');

        $this->assertSame(1, PsychosocialDocumentVersion::query()->count());
        $this->assertSame(0, PsychosocialDocumentValidation::query()->count());
    }
}
