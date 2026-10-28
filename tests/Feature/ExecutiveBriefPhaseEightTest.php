<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\SurveyAnswer;
use App\Models\SurveyCampaignRegistration;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\ExecutiveBriefService;
use App\Services\ExecutiveBriefSnapshotBuilder;
use App\Services\PsychosocialMeasureService;
use App\Services\SurveyResultCalculator;
use App\Support\CanonicalJson;
use App\Support\Nr1InstrumentMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class ExecutiveBriefPhaseEightTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Campanha com 29 respondentes: A=19, B=6, C=4, com survey_results gravados.
     *
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
                    'age_range' => 'faixa-etaria-25-34',
                    'tenure_range' => 'tempo-casa-1-3',
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

    private function release(object $campaign): PsychosocialDocumentVersion
    {
        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]));

        return PsychosocialDocumentVersion::query()->latest('id')->firstOrFail();
    }

    private function clientDownload(object $campaign): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($campaign->client)
            ->get(route('client.surveys.executive-brief.download', $campaign->fx->survey));
    }

    private function html(PsychosocialDocumentVersion $version): string
    {
        return app(ExecutiveBriefService::class)->renderHtml($version->snapshot);
    }

    public function test_release_creates_executive_brief_released_pending_validation_with_ready_pdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $survey = $campaign->fx->survey;

        $version = $this->release($campaign);

        $document = PsychosocialDocument::query()->sole();
        $this->assertSame(PsychosocialDocument::TYPE_EXECUTIVE_BRIEF, $document->type);
        $this->assertSame($version->id, $document->current_version_id);

        $this->assertSame(1, $version->version_number);
        $this->assertSame(PsychosocialDocumentVersion::KIND_RELEASED, $version->kind);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION, $version->seal);
        $this->assertNull($version->parent_version_id);
        $this->assertNull($version->validation_block);
        $this->assertSame($survey->method_version_id, $version->method_version_id);
        $this->assertSame($survey->instrument_version_id, $version->instrument_version_id);
        $this->assertSame(CanonicalJson::sha256($version->snapshot), $version->snapshot_sha256);
        $this->assertSame(CanonicalJson::sha256(['snapshot' => $version->snapshot, 'validation_block' => null]), $version->content_sha256);

        $this->assertSame(PsychosocialDocumentVersion::PDF_READY, $version->pdf_status);
        $this->assertNull($version->pdf_error);
        Storage::disk('local')->assertExists($version->pdf_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($version->pdf_path)), $version->pdf_sha256);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($version->pdf_path));
    }

    public function test_cascade_keeps_only_sector_a_in_snapshot_and_pdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);

        $this->assertSame(['Setor A'], array_column($version->snapshot['sectors'], 'department_name'));

        $snapshotJson = json_encode($version->snapshot, JSON_UNESCAPED_UNICODE);
        $html = $this->html($version);
        foreach (['snapshot' => $snapshotJson, 'html' => $html] as $label => $content) {
            $this->assertStringContainsString('Setor A', $content, $label);
            foreach (['Setor B', 'Setor C', 'Demais setores', 'faixa-etaria', 'tempo-casa', 'age_range', 'tenure_range'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $content, "{$label} não pode conter {$forbidden}.");
            }
        }

        $this->assertStringContainsString(ExecutiveBriefSnapshotBuilder::REFERENCE, $html);
        $this->assertStringContainsString(ExecutiveBriefSnapshotBuilder::DECISION_PENDING, $html);
        $this->assertStringContainsString('Faixa do questionário. Não é classificação de risco ocupacional do PGR.', $html);
        $this->assertSame(['Geral', $campaign->fx->section->title], array_column($version->snapshot['findings'], 'cut'));
        $this->assertSame([29, 29], array_column($version->snapshot['findings'], 'evidence_n'));
    }

    public function test_hse_with_pending_audit_releases_with_polarity_warning(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->survey->instrumentVersion->update([
            'instrument_code' => Nr1InstrumentMatrix::CODE_HSE_IT,
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_PENDING,
        ]);

        $version = $this->release($campaign);

        $this->assertSame('Polaridade não revalidada', $version->snapshot['polarity_warning']);
        $this->assertSame('2.414', $version->snapshot['overall']['score']);
        $this->assertStringContainsString('Polaridade não revalidada', $this->html($version));
        $this->assertStringNotContainsString('validado', mb_strtolower($this->html($version)));
    }

    public function test_null_eligible_and_empty_registration_stay_gaps_without_rate(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);
        $eligible = $version->snapshot['eligible'];

        $this->assertNull($eligible['count']);
        $this->assertNull($eligible['participation_label']);
        $this->assertSame('Aguardando total elegível', $eligible['hint']);
        $this->assertSame('Elegíveis não informados', $eligible['respondents_hint']);
        $this->assertSame([], array_filter($version->snapshot['registration'], fn ($value) => $value !== null));

        $html = $this->html($version);
        $this->assertStringContainsString('Aguardando total elegível', $html);
        $this->assertStringContainsString('Elegíveis não informados', $html);
        $this->assertStringNotContainsString(' de 29', $html);
        $this->assertStringContainsString(ExecutiveBriefSnapshotBuilder::NO_MEASURES, $html);
    }

    public function test_eligible_below_respondents_records_alert_without_rate(): void
    {
        $campaign = $this->seedCascadeCampaign();
        SurveyCampaignRegistration::query()->create([
            'survey_id' => $campaign->fx->survey->id,
            'company_id' => $campaign->fx->company->id,
            'eligible_count' => 20,
        ]);

        $eligible = $this->release($campaign)->snapshot['eligible'];

        $this->assertNotNull($eligible['alert']);
        $this->assertNull($eligible['participation_label']);
    }

    public function test_to_approve_measure_appears_as_a_aprovar(): void
    {
        $campaign = $this->seedCascadeCampaign();
        app(PsychosocialMeasureService::class)->createEngineSuggestion($campaign->fx->survey, [
            'factor_label' => 'Exigências elevadas',
            'measure_description' => 'Revisar volume e ritmo de trabalho.',
        ]);

        $version = $this->release($campaign);

        $this->assertSame(['A aprovar'], array_column($version->snapshot['measures'], 'status_label'));
        $this->assertStringContainsString('A aprovar', $this->html($version));
        $this->assertSame(1, \App\Models\PsychosocialMeasure::query()->count());
    }

    public function test_recalculating_after_release_keeps_snapshot_and_pdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);
        $fileBytes = Storage::disk('local')->get($version->pdf_path);

        app(SurveyResultCalculator::class)->recalculate($campaign->fx->survey->fresh());
        $this->assertNotSame('2.414', (string) SurveyResult::query()
            ->where('survey_id', $campaign->fx->survey->id)
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->value('average_score'));

        $after = $version->fresh();
        $this->assertSame($version->snapshot_sha256, $after->snapshot_sha256);
        $this->assertSame($version->content_sha256, $after->content_sha256);
        $this->assertSame($version->pdf_sha256, $after->pdf_sha256);
        $this->assertSame($version->snapshot, $after->snapshot);
        $this->assertSame($fileBytes, Storage::disk('local')->get($after->pdf_path));
    }

    public function test_ready_download_serves_stored_file_without_dompdf_and_logs_access(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);

        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        $response = $this->clientDownload($campaign)->assertOk();
        $this->assertSame(Storage::disk('local')->get($version->pdf_path), $response->streamedContent());

        $log = PsychosocialAccessLog::query()->sole();
        $this->assertSame('document_download', $log->resource);
        $this->assertSame($version->id, $log->document_version_id);
        $this->assertSame($campaign->client->id, $log->user_id);
        $this->assertSame($campaign->fx->survey->id, $log->survey_id);
    }

    public function test_missing_file_or_divergent_hash_returns_error_without_regenerating(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);
        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        Storage::disk('local')->put($version->pdf_path, 'conteúdo adulterado');
        $this->clientDownload($campaign)->assertStatus(409)->assertSee('não confere com o hash');
        $this->assertSame('conteúdo adulterado', Storage::disk('local')->get($version->pdf_path));

        Storage::disk('local')->delete($version->pdf_path);
        $this->clientDownload($campaign)->assertStatus(409)->assertSee('não foi encontrado');
        Storage::disk('local')->assertMissing($version->pdf_path);

        $this->assertSame($version->pdf_sha256, $version->fresh()->pdf_sha256);
        $this->assertSame(0, PsychosocialAccessLog::query()->count());
    }

    public function test_queued_version_answers_in_preparation_without_dompdf(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);
        \Illuminate\Support\Facades\DB::table('psychosocial_document_versions')
            ->where('id', $version->id)
            ->update(['pdf_status' => PsychosocialDocumentVersion::PDF_QUEUED]);
        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        $this->clientDownload($campaign)->assertStatus(409)->assertSee('em preparação');
        $this->assertSame(0, PsychosocialAccessLog::query()->count());
    }

    public function test_pdf_failure_records_failed_without_file(): void
    {
        $campaign = $this->seedCascadeCampaign();
        Pdf::shouldReceive('loadView')->once()->andThrow(new RuntimeException('Falha simulada do DomPDF'));

        $version = $this->release($campaign);

        $this->assertSame(PsychosocialDocumentVersion::PDF_FAILED, $version->pdf_status);
        $this->assertNull($version->pdf_path);
        $this->assertNull($version->pdf_sha256);
        $this->assertStringContainsString('Falha simulada', (string) $version->pdf_error);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->clientDownload($campaign)->assertStatus(409);
    }

    public function test_ready_pdf_path_and_hash_are_frozen(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $version = $this->release($campaign);

        $this->expectException(\LogicException::class);
        $version->update(['pdf_sha256' => str_repeat('0', 64)]);
    }

    public function test_second_release_creates_version_2_and_keeps_version_1(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $first = $this->release($campaign);
        $firstState = $first->only(['snapshot_sha256', 'content_sha256', 'pdf_path', 'pdf_sha256', 'pdf_status', 'seal']);

        $second = $this->release($campaign);

        $this->assertSame(2, $second->version_number);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($firstState, $first->fresh()->only(array_keys($firstState)));
        Storage::disk('local')->assertExists($first->pdf_path);
        Storage::disk('local')->assertExists($second->pdf_path);
        $this->assertSame($second->id, PsychosocialDocument::query()->sole()->current_version_id);
    }

    public function test_only_executive_brief_is_created(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $this->release($campaign);
        $this->release($campaign);

        $this->assertSame([PsychosocialDocument::TYPE_EXECUTIVE_BRIEF], PsychosocialDocument::query()->pluck('type')->all());
        $this->assertSame(0, PsychosocialDocument::query()
            ->whereIn('type', [PsychosocialDocument::TYPE_PGR_SUBSIDY, PsychosocialDocument::TYPE_EVIDENCE_DOSSIER])
            ->count());
    }

    public function test_client_without_version_has_no_download(): void
    {
        $campaign = $this->seedCascadeCampaign();

        $this->clientDownload($campaign)->assertNotFound();
        $this->actingAs($campaign->client)
            ->get(route('client.surveys.psychosocial-dashboard', $campaign->fx->survey))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('executiveBrief', null));
    }

    public function test_other_company_cannot_release_or_download(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $other = $this->createSurveyFixture();
        $this->release($campaign);
        $outsider = User::factory()->companyAdmin($other->company->id)->create();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.executive-brief.release', [$other->company->id, $campaign->fx->survey->id]))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('client.surveys.executive-brief.download', $campaign->fx->survey))
            ->assertNotFound();

        $this->assertSame(1, PsychosocialDocumentVersion::query()->count());
    }
}
