<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActionPlanItem;
use App\Models\Department;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentAttachment;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialMeasure;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use App\Models\User;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\SurveyResultCalculator;
use App\Support\Nr1InstrumentMatrix;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class EvidenceDossierAndMeasuresPhaseTenElevenTest extends TestCase
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

    public function test_hse_pending_audit_does_not_release_dossier_or_create_document(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $campaign->fx->survey->instrumentVersion->update([
            'instrument_code' => Nr1InstrumentMatrix::CODE_HSE_IT,
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_PENDING,
        ]);

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.evidence-dossier.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertRedirect(route('admin.companies.surveys.action-plan.edit', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHas('error');

        $this->assertSame(0, PsychosocialDocument::query()->where('type', PsychosocialDocument::TYPE_EVIDENCE_DOSSIER)->count());
        $this->assertSame(0, PsychosocialDocumentAttachment::query()->count());
    }

    public function test_dossier_with_one_attachment_freezes_the_file_list_and_blank_inventory(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $file = UploadedFile::fake()->create('avaliacao-aep.pdf', 20, 'application/pdf');

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.evidence-dossier.release', [$campaign->fx->company->id, $campaign->fx->survey->id]), [
                'attachments' => [
                    ['kind' => 'aep', 'file' => $file],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $released = PsychosocialDocumentVersion::query()->sole();
        $this->assertSame(PsychosocialDocumentVersion::KIND_RELEASED, $released->kind);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION, $released->seal);
        $this->assertSame(PsychosocialDocumentVersion::PDF_READY, $released->pdf_status);
        $this->assertSame('evidence_dossier.v1', $released->snapshot_schema);
        $this->assertSame([
            ['kind' => 'aep', 'kind_label' => 'AEP', 'file_name' => 'avaliacao-aep.pdf', 'sha256' => $released->snapshot['attachments'][0]['sha256']],
        ], $released->snapshot['attachments']);
        $this->assertArrayNotHasKey('file_path', $released->snapshot['attachments'][0]);
        foreach (['severity', 'probability', 'risk_level', 'signature'] as $key) {
            $this->assertNull($released->snapshot['inventory'][$key]);
        }

        $attachment = PsychosocialDocumentAttachment::query()->sole();
        $this->assertSame($released->id, $attachment->version_id);
        $this->assertSame($released->snapshot['attachments'][0]['sha256'], $attachment->sha256);
        $this->assertTrue(Storage::disk('local')->exists($attachment->file_path));
        $this->assertSame('%PDF', substr((string) Storage::disk('local')->get($released->pdf_path), 0, 4));
        $this->assertSame($released->pdf_sha256, hash('sha256', (string) Storage::disk('local')->get($released->pdf_path)));

        $html = app(PsychosocialDocumentService::class)->renderHtml(PsychosocialDocument::TYPE_EVIDENCE_DOSSIER, $released->snapshot);
        $this->assertStringContainsString('avaliacao-aep.pdf', $html);
        $this->assertStringContainsString('Não informado', $html);
        $this->assertStringNotContainsString($attachment->file_path, $html);

        $before = $released->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']);
        app(SurveyResultCalculator::class)->recalculate($campaign->fx->survey->fresh());
        $this->assertSame($before, $released->fresh()->only(['snapshot_sha256', 'content_sha256', 'pdf_sha256', 'pdf_path']));

        $this->actingAs($campaign->client)
            ->post(route('client.surveys.psychosocial-documents.validate', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_EVIDENCE_DOSSIER,
            ]), ['declaration_accepted' => '1'])
            ->assertSessionHasNoErrors();

        $validated = PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->sole();
        $this->assertSame($released->snapshot_sha256, $validated->snapshot_sha256);
        $this->assertNotSame($released->content_sha256, $validated->content_sha256);
        $this->assertSame(1, $validated->attachments()->count());

        $download = $this->actingAs($campaign->admin)
            ->get(route('admin.companies.surveys.evidence-dossier.attachments.download', [
                $campaign->fx->company->id,
                $campaign->fx->survey->id,
                $validated->attachments()->firstOrFail()->id,
            ]))
            ->assertOk();
        $this->assertSame(Storage::disk('local')->get($attachment->file_path), $download->streamedContent());
        $this->assertSame(1, PsychosocialAccessLog::query()->where('resource', 'document_download')->count());

        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();
        $pdf = $this->actingAs($campaign->client)
            ->get(route('client.surveys.psychosocial-documents.download', [
                $campaign->fx->survey->id,
                PsychosocialDocument::TYPE_EVIDENCE_DOSSIER,
            ]))
            ->assertOk();
        $this->assertSame(Storage::disk('local')->get($validated->pdf_path), $pdf->streamedContent());
    }

    public function test_dossier_without_files_lists_no_attachments(): void
    {
        $campaign = $this->seedCascadeCampaign();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.evidence-dossier.release', [$campaign->fx->company->id, $campaign->fx->survey->id]))
            ->assertSessionHas('success');

        $released = PsychosocialDocumentVersion::query()->sole();
        $this->assertSame([], $released->snapshot['attachments']);
        $this->assertSame(0, PsychosocialDocumentAttachment::query()->count());

        $html = app(PsychosocialDocumentService::class)->renderHtml(PsychosocialDocument::TYPE_EVIDENCE_DOSSIER, $released->snapshot);
        $this->assertStringContainsString('Nenhum anexo registrado', $html);
    }

    public function test_manual_measure_starts_to_approve_and_implemented_records_the_user(): void
    {
        $campaign = $this->seedCascadeCampaign();
        $planItems = ActionPlanItem::query()->count();

        $this->actingAs($campaign->admin)
            ->post(route('admin.companies.surveys.measures.store', [$campaign->fx->company->id, $campaign->fx->survey->id]), [
                'factor_label' => 'Demandas do trabalho',
                'measure_description' => 'Revisar a distribuição das tarefas do setor.',
                'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
                'responsible_text' => 'Coordenação',
            ])
            ->assertSessionHas('success');

        $measure = PsychosocialMeasure::query()->sole();
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $measure->status);
        $this->assertSame(PsychosocialMeasure::ORIGIN_MANUAL, $measure->origin);
        $this->assertNull($measure->implemented_by);
        $this->assertSame($planItems, ActionPlanItem::query()->count());

        $this->actingAs($campaign->admin)
            ->patch(route('admin.companies.surveys.measures.status', [$campaign->fx->company->id, $campaign->fx->survey->id, $measure->id]), [
                'status' => PsychosocialMeasure::STATUS_IMPLEMENTED,
            ])
            ->assertSessionHas('success');

        $measure->refresh();
        $this->assertSame(PsychosocialMeasure::STATUS_IMPLEMENTED, $measure->status);
        $this->assertSame($campaign->admin->id, $measure->implemented_by);
        $this->assertNotNull($measure->implemented_at);
        $this->assertSame($planItems, ActionPlanItem::query()->count());
    }
}
