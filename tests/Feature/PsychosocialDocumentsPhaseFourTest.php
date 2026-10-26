<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\WipeCompanyDataCommand;
use App\Exceptions\PsychosocialDocumentException;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentAttachment;
use App\Models\PsychosocialDocumentValidation;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PsychosocialDocumentsPhaseFourTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_20_180000_create_psychosocial_documents_tables.php';

    private const TABLES = [
        'psychosocial_documents',
        'psychosocial_document_versions',
        'psychosocial_document_validations',
        'psychosocial_document_attachments',
        'psychosocial_access_logs',
    ];

    private function migration(): object
    {
        return require database_path(self::MIGRATION);
    }

    private function createDocument(Survey $survey, string $type = PsychosocialDocument::TYPE_PGR_SUBSIDY): PsychosocialDocument
    {
        return PsychosocialDocument::query()->create([
            'survey_id' => $survey->id,
            'company_id' => $survey->company_id,
            'type' => $type,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReleased(PsychosocialDocument $document, array $overrides = []): PsychosocialDocumentVersion
    {
        $snapshot = ['schema' => 'pgr_subsidy.v1', 'factors' => [['label' => 'Demandas', 'band' => 'yellow']]];
        $survey = $document->survey;

        return PsychosocialDocumentVersion::query()->create(array_merge([
            'document_id' => $document->id,
            'version_number' => 1,
            'public_identifier' => 'PGR-'.Str::upper(Str::random(10)),
            'kind' => PsychosocialDocumentVersion::KIND_RELEASED,
            'method_version_id' => $survey->method_version_id,
            'instrument_version_id' => $survey->instrument_version_id,
            'snapshot_schema' => 'pgr_subsidy.v1',
            'snapshot' => $snapshot,
            'snapshot_sha256' => hash('sha256', json_encode($snapshot)),
            'content_sha256' => hash('sha256', 'released:'.json_encode($snapshot)),
            'seal' => PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION,
        ], $overrides));
    }

    private function createValidated(PsychosocialDocumentVersion $released, int $versionNumber = 2): PsychosocialDocumentVersion
    {
        $block = ['path' => 'in_system', 'name' => 'Responsável SST'];

        return PsychosocialDocumentVersion::query()->create([
            'document_id' => $released->document_id,
            'version_number' => $versionNumber,
            'public_identifier' => 'PGR-'.Str::upper(Str::random(10)),
            'kind' => PsychosocialDocumentVersion::KIND_VALIDATED,
            'parent_version_id' => $released->id,
            'method_version_id' => $released->method_version_id,
            'instrument_version_id' => $released->instrument_version_id,
            'snapshot_schema' => $released->snapshot_schema,
            'snapshot' => $released->snapshot,
            'snapshot_sha256' => $released->snapshot_sha256,
            'validation_block' => $block,
            'content_sha256' => hash('sha256', 'validated:'.json_encode($released->snapshot).json_encode($block)),
            'seal' => PsychosocialDocumentVersion::SEAL_VALIDATED,
        ]);
    }

    public function test_document_header_exists_without_any_version(): void
    {
        $fx = $this->createSurveyFixture();

        $document = $this->createDocument($fx->survey);

        $this->assertNull($document->fresh()->current_version_id);
        $this->assertSame(0, $document->versions()->count());

        try {
            // «technical_report» virou tipo válido na Etapa 7 (relatório técnico + plano); o exemplo inválido é outro.
            $this->createDocument($fx->survey, 'relatorio_inexistente');
            $this->fail('Tipo fora da lista deveria ser rejeitado.');
        } catch (PsychosocialDocumentException) {
        }

        $this->expectException(QueryException::class);
        $this->createDocument($fx->survey);
    }

    public function test_released_version_is_born_pending_validation_without_pdf(): void
    {
        $fx = $this->createSurveyFixture();
        $version = $this->createReleased($this->createDocument($fx->survey))->fresh();

        $this->assertSame(PsychosocialDocumentVersion::KIND_RELEASED, $version->kind);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION, $version->seal);
        $this->assertNull($version->parent_version_id);
        $this->assertNull($version->validation_block);
        $this->assertNull($version->pdf_status);
        $this->assertNull($version->pdf_path);
        $this->assertNull($version->pdf_sha256);
        $this->assertNull($version->pdf_error);
        $this->assertSame($fx->survey->method_version_id, $version->method_version_id);
        $this->assertSame($fx->survey->instrument_version_id, $version->instrument_version_id);
    }

    public function test_released_version_cannot_carry_validated_seal_or_foreign_method(): void
    {
        $fx = $this->createSurveyFixture();
        $document = $this->createDocument($fx->survey);

        try {
            $this->createReleased($document, ['seal' => PsychosocialDocumentVersion::SEAL_VALIDATED]);
            $this->fail('Released com selo validated deveria ser rejeitada.');
        } catch (PsychosocialDocumentException) {
        }

        $this->expectException(PsychosocialDocumentException::class);
        $this->createReleased($document, ['method_version_id' => $fx->survey->method_version_id + 1000]);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function frozenFields(): array
    {
        return [
            'snapshot' => ['snapshot', ['factors' => []]],
            'snapshot_sha256' => ['snapshot_sha256', str_repeat('a', 64)],
            'content_sha256' => ['content_sha256', str_repeat('b', 64)],
            'seal' => ['seal', PsychosocialDocumentVersion::SEAL_VALIDATED],
        ];
    }

    #[DataProvider('frozenFields')]
    public function test_model_refuses_to_change_snapshot_hashes_or_seal(string $field, mixed $value): void
    {
        $fx = $this->createSurveyFixture();
        $version = $this->createReleased($this->createDocument($fx->survey));
        $original = $version->fresh()->getAttribute($field);

        try {
            $version->update([$field => $value]);
            $this->fail("O campo {$field} não deveria mudar.");
        } catch (LogicException) {
            $this->assertEquals($original, $version->fresh()->getAttribute($field));
        }
    }

    public function test_version_cannot_be_deleted_and_only_pdf_state_evolves(): void
    {
        $fx = $this->createSurveyFixture();
        $version = $this->createReleased($this->createDocument($fx->survey));

        $version->update(['pdf_status' => PsychosocialDocumentVersion::PDF_QUEUED]);
        $this->assertSame(PsychosocialDocumentVersion::PDF_QUEUED, $version->fresh()->pdf_status);

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_validated_reuses_released_snapshot_with_its_own_content_hash(): void
    {
        $fx = $this->createSurveyFixture();
        $released = $this->createReleased($this->createDocument($fx->survey));

        $validated = $this->createValidated($released)->fresh();

        $this->assertSame($released->snapshot_sha256, $validated->snapshot_sha256);
        $this->assertSame($released->fresh()->snapshot, $validated->snapshot);
        $this->assertNotSame($released->content_sha256, $validated->content_sha256);
        $this->assertSame(PsychosocialDocumentVersion::SEAL_VALIDATED, $validated->seal);

        $this->expectException(PsychosocialDocumentException::class);
        PsychosocialDocumentVersion::query()->create(array_merge($validated->only([
            'document_id', 'kind', 'parent_version_id', 'method_version_id', 'instrument_version_id',
            'snapshot_schema', 'validation_block', 'content_sha256', 'seal',
        ]), [
            'version_number' => 3,
            'public_identifier' => 'PGR-OUTRO',
            'snapshot' => ['factors' => []],
            'snapshot_sha256' => hash('sha256', 'outro'),
        ]));
    }

    public function test_informed_validation_without_proof_is_not_created(): void
    {
        $fx = $this->createSurveyFixture();
        $recorder = User::factory()->create();
        $validated = $this->createValidated($this->createReleased($this->createDocument($fx->survey)));

        try {
            PsychosocialDocumentValidation::query()->create([
                'version_id' => $validated->id,
                'path' => PsychosocialDocumentValidation::PATH_INFORMED,
                'informed_name' => 'Maria SST',
                'informed_role' => 'Técnica de segurança',
                'informed_date' => '2026-10-20',
                'recorded_by' => $recorder->id,
            ]);
            $this->fail('Validação informada sem comprovante deveria ser rejeitada.');
        } catch (PsychosocialDocumentException) {
            $this->assertSame(0, PsychosocialDocumentValidation::query()->count());
        }
    }

    public function test_down_without_versions_drops_tables_and_keeps_survey_results(): void
    {
        $fx = $this->createSurveyFixture();
        $this->createDocument($fx->survey);
        DB::table('survey_results')->insert([
            'survey_id' => $fx->survey->id,
            'average_score' => '2.500',
            'risk_level' => 'yellow',
            'respondent_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $resultsBefore = DB::table('survey_results')->count();

        $this->migration()->down();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} deveria ter sido removida.");
        }
        $this->assertSame($resultsBefore, DB::table('survey_results')->count());
    }

    public function test_down_with_one_version_aborts_and_tables_remain(): void
    {
        $fx = $this->createSurveyFixture();
        $this->createReleased($this->createDocument($fx->survey));

        try {
            $this->migration()->down();
            $this->fail('O rollback deveria ter sido bloqueado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('psychosocial_document_versions', $exception->getMessage());
        }

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} deveria continuar existindo.");
        }
        $this->assertSame(1, PsychosocialDocumentVersion::query()->count());
    }

    public function test_wipe_removes_documents_only_from_target_company_and_keeps_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('psychosocial/anexo.pdf', 'conteúdo');

        $target = $this->createSurveyFixture();
        $other = $this->createSurveyFixture();
        $user = User::factory()->create();

        $targetDocument = $this->createDocument($target->survey);
        $released = $this->createReleased($targetDocument);
        $validated = $this->createValidated($released);
        $targetDocument->update(['current_version_id' => $validated->id]);
        PsychosocialDocumentValidation::query()->create([
            'version_id' => $validated->id,
            'path' => PsychosocialDocumentValidation::PATH_IN_SYSTEM,
            'user_id' => $user->id,
            'validated_at' => now(),
            'declaration_text' => 'Declaro que revisei o subsídio.',
            'declaration_accepted' => true,
            'recorded_by' => $user->id,
        ]);
        PsychosocialDocumentAttachment::query()->create([
            'version_id' => $validated->id,
            'kind' => 'aep',
            'file_path' => 'psychosocial/anexo.pdf',
            'file_name' => 'anexo.pdf',
            'sha256' => hash('sha256', 'conteúdo'),
            'uploaded_by' => $user->id,
        ]);
        PsychosocialAccessLog::query()->create([
            'user_id' => $user->id,
            'company_id' => $target->company->id,
            'survey_id' => $target->survey->id,
            'resource' => 'document_download',
            'document_version_id' => $validated->id,
        ]);

        $otherDocument = $this->createDocument($other->survey);
        $otherVersion = $this->createReleased($otherDocument);

        $wipe = new ReflectionMethod(WipeCompanyDataCommand::class, 'wipe');
        $wipe->invoke(app(WipeCompanyDataCommand::class), $target->company->id);

        $this->assertSame(0, PsychosocialDocument::query()->where('company_id', $target->company->id)->count());
        $this->assertSame(0, PsychosocialDocumentVersion::query()->whereIn('id', [$released->id, $validated->id])->count());
        $this->assertSame(0, PsychosocialDocumentValidation::query()->count());
        $this->assertSame(0, PsychosocialDocumentAttachment::query()->count());

        $this->assertNotNull($otherDocument->fresh());
        $this->assertNotNull($otherVersion->fresh());
        $this->assertSame(1, PsychosocialAccessLog::query()->count());
        Storage::disk('local')->assertExists('psychosocial/anexo.pdf');
    }

    public function test_schema_and_models_never_call_dompdf_nor_queue_jobs(): void
    {
        Queue::fake();
        Pdf::shouldReceive('loadView', 'loadHTML', 'loadFile', 'setPaper')->never();

        $fx = $this->createSurveyFixture();
        $document = $this->createDocument($fx->survey, PsychosocialDocument::TYPE_EVIDENCE_DOSSIER);
        $released = $this->createReleased($document);
        $validated = $this->createValidated($released);
        $document->update(['current_version_id' => $validated->id]);

        $this->assertSame($validated->id, $document->fresh()->current_version_id);
        $this->assertNull($validated->fresh()->pdf_status);
        Queue::assertNothingPushed();
    }
}
