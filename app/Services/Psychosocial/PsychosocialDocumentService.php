<?php

declare(strict_types=1);

namespace App\Services\Psychosocial;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentAttachment;
use App\Models\PsychosocialDocumentValidation;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\User;
use App\Services\ExecutiveBriefSnapshotBuilder;
use App\Services\ReportGenerator;
use App\Support\CanonicalJson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Ciclo de vida dos documentos novos (resumo executivo e subsídio AEP/PGR):
 * liberação congela o snapshot numa versão released; a validação cria uma filha validated
 * com o mesmo snapshot e o bloco de validação. O PDF é sempre impressão do snapshot congelado.
 */
class PsychosocialDocumentService
{
    public const DISK = 'local';

    public const IN_SYSTEM_DECLARATION = 'Declaro que revisei este documento e que a validação se refere ao conteúdo congelado desta versão.';

    /** @var array<string, array{builder: class-string<DocumentSnapshotBuilder>, view: string, prefix: string, file: string, label: string}> */
    private const TYPES = [
        PsychosocialDocument::TYPE_EXECUTIVE_BRIEF => [
            'builder' => ExecutiveBriefSnapshotBuilder::class,
            'view' => 'psychosocial.executive_brief',
            'prefix' => 'RE',
            'file' => 'resumo-executivo',
            'label' => 'Resumo executivo',
        ],
        PsychosocialDocument::TYPE_PGR_SUBSIDY => [
            'builder' => PgrSubsidySnapshotBuilder::class,
            'view' => 'psychosocial.pgr_subsidy',
            'prefix' => 'SUB',
            'file' => 'subsidio-aep-pgr',
            'label' => 'Subsídio AEP/PGR',
        ],
        PsychosocialDocument::TYPE_EVIDENCE_DOSSIER => [
            'builder' => EvidenceDossierSnapshotBuilder::class,
            'view' => 'psychosocial.evidence_dossier',
            'prefix' => 'DOS',
            'file' => 'dossie-evidencias',
            'label' => 'Dossiê de evidências',
        ],
        PsychosocialDocument::TYPE_TECHNICAL_REPORT => [
            'builder' => TechnicalReportSnapshotBuilder::class,
            'view' => 'psychosocial.technical_report',
            'prefix' => 'RT',
            'file' => 'relatorio-tecnico-plano-de-acao',
            'label' => 'Relatório técnico e plano de ação',
        ],
    ];

    /** Documentos finais do fluxo V11 (Caderno §12). Subsídio AEP/PGR e dossiê ficam só nas campanhas antigas. */
    public const FINAL_TYPES = [
        PsychosocialDocument::TYPE_EXECUTIVE_BRIEF,
        PsychosocialDocument::TYPE_TECHNICAL_REPORT,
    ];

    public function __construct(private readonly ReportGenerator $reportGenerator)
    {
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return array_keys(self::TYPES);
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    /**
     * @param  list<array{kind: string, file_path: string, file_name: string, sha256: string, uploaded_by: int}>  $preparedAttachments
     */
    public function release(Survey $survey, string $type, array $preparedAttachments = []): PsychosocialDocumentVersion
    {
        $builder = $this->builder($type);
        $builder->assertReleasable($survey);
        $snapshot = $builder->build($survey);

        if ($type === PsychosocialDocument::TYPE_EVIDENCE_DOSSIER) {
            $snapshot['attachments'] = array_map(fn (array $file): array => [
                'kind' => $file['kind'],
                'kind_label' => PsychosocialDocumentAttachment::KIND_LABELS[$file['kind']] ?? $file['kind'],
                'file_name' => $file['file_name'],
                'sha256' => $file['sha256'],
            ], $preparedAttachments);
        }

        $version = DB::transaction(function () use ($survey, $type, $builder, $snapshot, $preparedAttachments): PsychosocialDocumentVersion {
            $document = $this->lockedDocument($survey, $type);
            $versionNumber = $this->nextVersionNumber($document);

            $version = PsychosocialDocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'public_identifier' => $this->publicIdentifier($survey, $type, $versionNumber),
                'kind' => PsychosocialDocumentVersion::KIND_RELEASED,
                'method_version_id' => $survey->method_version_id,
                'instrument_version_id' => $survey->instrument_version_id,
                'snapshot_schema' => $builder->schema(),
                'snapshot' => $snapshot,
                'snapshot_sha256' => CanonicalJson::sha256($snapshot),
                'content_sha256' => self::contentHash($snapshot, null),
                'seal' => PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION,
                'pdf_status' => PsychosocialDocumentVersion::PDF_QUEUED,
            ]);

            foreach ($preparedAttachments as $file) {
                PsychosocialDocumentAttachment::query()->create([
                    'version_id' => $version->id,
                    'kind' => $file['kind'],
                    'file_path' => $file['file_path'],
                    'file_name' => $file['file_name'],
                    'sha256' => $file['sha256'],
                    'uploaded_by' => $file['uploaded_by'],
                ]);
            }

            $document->update(['current_version_id' => $version->id]);

            return $version;
        });

        $this->printPdf($version, $type);

        return $version->fresh();
    }

    /**
     * Libera o dossiê. Os arquivos entram no snapshot e ficam ligados à versão.
     * Sem arquivo, o dossiê sai com a lista de anexos vazia.
     *
     * @param  list<array{kind: string, file: UploadedFile}>  $uploads
     */
    public function releaseDossier(Survey $survey, User $user, array $uploads): PsychosocialDocumentVersion
    {
        $this->builder(PsychosocialDocument::TYPE_EVIDENCE_DOSSIER)->assertReleasable($survey);

        $prepared = [];
        $paths = [];

        try {
            foreach ($uploads as $upload) {
                $file = $upload['file'];
                $sha = hash_file('sha256', (string) $file->getRealPath());
                $path = $file->storeAs(
                    sprintf('psychosocial-documents/%d/evidence_dossier/attachments', $survey->id),
                    Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'bin'),
                    self::DISK,
                );

                if ($path === false) {
                    throw new PsychosocialDocumentException('Não foi possível gravar o anexo do dossiê.');
                }

                $paths[] = $path;
                $prepared[] = [
                    'kind' => $upload['kind'],
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'sha256' => $sha,
                    'uploaded_by' => $user->id,
                ];
            }

            return $this->release($survey, PsychosocialDocument::TYPE_EVIDENCE_DOSSIER, $prepared);
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Validação pelo cliente, dentro do sistema, com a declaração fixa aceita.
     */
    public function validateInSystem(PsychosocialDocumentVersion $released, User $user, bool $declarationAccepted): PsychosocialDocumentVersion
    {
        if (! $declarationAccepted) {
            throw new PsychosocialDocumentException('A validação exige aceitar a declaração.');
        }

        $validatedAt = now();

        return $this->createValidated(
            $released,
            [
                'path' => PsychosocialDocumentValidation::PATH_IN_SYSTEM,
                'validated_by' => ['id' => $user->id, 'name' => $user->name],
                'validated_at' => $validatedAt->toIso8601String(),
                'declaration' => self::IN_SYSTEM_DECLARATION,
            ],
            [
                'path' => PsychosocialDocumentValidation::PATH_IN_SYSTEM,
                'user_id' => $user->id,
                'validated_at' => $validatedAt,
                'declaration_text' => self::IN_SYSTEM_DECLARATION,
                'declaration_accepted' => true,
                'recorded_by' => $user->id,
                'recorded_at' => $validatedAt,
            ],
        );
    }

    /**
     * Validação feita fora do sistema e registrada pelo Admin Talents, com comprovante obrigatório.
     *
     * @param  array{name: string, role: string, registry: ?string, date: string}  $informed
     */
    public function validateInformed(PsychosocialDocumentVersion $released, User $admin, array $informed, ?UploadedFile $proof): PsychosocialDocumentVersion
    {
        if ($proof === null || ! $proof->isValid()) {
            throw new PsychosocialDocumentException('A validação informada exige o arquivo do comprovante.');
        }

        $document = $released->document;
        $proofSha256 = hash_file('sha256', $proof->getRealPath());
        $proofName = $proof->getClientOriginalName();
        $proofPath = $proof->storeAs(
            sprintf('psychosocial-documents/%d/%s/validations', $document->survey_id, $document->type),
            Str::uuid().'.'.($proof->getClientOriginalExtension() ?: 'bin'),
            self::DISK,
        );

        if ($proofPath === false) {
            throw new PsychosocialDocumentException('Não foi possível gravar o comprovante.');
        }

        $recordedAt = now();
        $informedDate = Carbon::parse($informed['date'])->toDateString();

        try {
            return $this->createValidated(
                $released,
                [
                    'path' => PsychosocialDocumentValidation::PATH_INFORMED,
                    'name' => $informed['name'],
                    'role' => $informed['role'],
                    'registry' => $informed['registry'],
                    'date' => $informedDate,
                    'proof' => ['file_name' => $proofName, 'sha256' => $proofSha256],
                    'recorded_by' => ['id' => $admin->id, 'name' => $admin->name],
                    'recorded_at' => $recordedAt->toIso8601String(),
                ],
                [
                    'path' => PsychosocialDocumentValidation::PATH_INFORMED,
                    'informed_name' => $informed['name'],
                    'informed_role' => $informed['role'],
                    'informed_registry' => $informed['registry'],
                    'informed_date' => $informedDate,
                    'proof_file_path' => $proofPath,
                    'proof_file_name' => $proofName,
                    'proof_sha256' => $proofSha256,
                    'recorded_by' => $admin->id,
                    'recorded_at' => $recordedAt,
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($proofPath);

            throw $exception;
        }
    }

    public function currentVersion(Survey $survey, string $type): ?PsychosocialDocumentVersion
    {
        return PsychosocialDocument::query()
            ->where('survey_id', $survey->id)
            ->where('type', $type)
            ->first()
            ?->currentVersion;
    }

    /**
     * @return array{type: string, label: string, version_number: int, kind: string, seal: string, pdf_status: ?string, released_at: ?string, validatable: bool}|null
     */
    public function currentVersionSummary(Survey $survey, string $type): ?array
    {
        $version = $this->currentVersion($survey, $type);

        return $version ? [
            'type' => $type,
            'label' => self::label($type),
            'version_number' => $version->version_number,
            'kind' => $version->kind,
            'seal' => $version->seal,
            'pdf_status' => $version->pdf_status,
            'released_at' => $version->created_at?->toIso8601String(),
            'validatable' => $this->isValidatable($version),
            'attachments' => $type === PsychosocialDocument::TYPE_EVIDENCE_DOSSIER
                ? $version->attachments->map(fn (PsychosocialDocumentAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'kind' => $attachment->kind,
                    'kind_label' => PsychosocialDocumentAttachment::KIND_LABELS[$attachment->kind] ?? $attachment->kind,
                    'file_name' => $attachment->file_name,
                ])->all()
                : [],
        ] : null;
    }

    /**
     * Um item por tipo suportado; tipos ainda não liberados vêm com version_number nulo.
     *
     * @return list<array<string, mixed>>
     */
    public function documentsSummary(Survey $survey): array
    {
        // Campanha V11: os documentos vivem na Central de documentos (fluxo de revisão); estas telas antigas
        // não listam nada. Nas antigas, só os três documentos de sempre (o relatório técnico é do fluxo V11).
        if ($survey->usesReviewFlow()) {
            return [];
        }

        return array_map(
            fn (string $type): array => $this->currentVersionSummary($survey, $type) ?? [
                'type' => $type,
                'label' => self::label($type),
                'version_number' => null,
                'kind' => null,
                'seal' => null,
                'pdf_status' => null,
                'released_at' => null,
                'validatable' => false,
                'attachments' => [],
            ],
            array_values(array_diff(self::supportedTypes(), [PsychosocialDocument::TYPE_TECHNICAL_REPORT])),
        );
    }

    public function isValidatable(PsychosocialDocumentVersion $version): bool
    {
        return $version->kind === PsychosocialDocumentVersion::KIND_RELEASED
            && ! PsychosocialDocumentVersion::query()->where('parent_version_id', $version->id)->exists();
    }

    /**
     * Entrega o arquivo gravado de uma versão. Nunca chama o DomPDF nem regenera.
     */
    public function download(PsychosocialDocumentVersion $version, User $user, ?string $ip): StreamedResponse
    {
        if ($version->pdf_status === PsychosocialDocumentVersion::PDF_QUEUED) {
            throw new PsychosocialDocumentException('O PDF desta versão está em preparação.');
        }

        if ($version->pdf_status !== PsychosocialDocumentVersion::PDF_READY) {
            throw new PsychosocialDocumentException('O PDF desta versão não foi gerado. Libere uma nova versão.');
        }

        $disk = Storage::disk(self::DISK);
        if (! $version->pdf_path || ! $disk->exists($version->pdf_path)) {
            throw new PsychosocialDocumentException('O arquivo desta versão não foi encontrado.');
        }

        $bytes = $disk->get($version->pdf_path);
        if (! hash_equals((string) $version->pdf_sha256, hash('sha256', (string) $bytes))) {
            throw new PsychosocialDocumentException('O arquivo desta versão não confere com o hash registrado.');
        }

        $document = $version->document;
        PsychosocialAccessLog::query()->create([
            'user_id' => $user->id,
            'company_id' => $document->company_id,
            'survey_id' => $document->survey_id,
            'resource' => 'document_download',
            'document_version_id' => $version->id,
            'ip' => $ip,
        ]);

        return response()->streamDownload(
            function () use ($bytes): void {
                echo $bytes;
            },
            self::TYPES[$document->type]['file'].'-'.$version->public_identifier.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $validationBlock
     */
    public function renderHtml(string $type, array $snapshot, ?array $validationBlock = null): string
    {
        return view($this->config($type)['view'], ['snapshot' => $snapshot, 'validation' => $validationBlock])->render();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $validationBlock
     */
    public static function contentHash(array $snapshot, ?array $validationBlock): string
    {
        return CanonicalJson::sha256(['snapshot' => $snapshot, 'validation_block' => $validationBlock]);
    }

    /**
     * @param  array<string, mixed>  $validationBlock
     * @param  array<string, mixed>  $validationRow
     */
    private function createValidated(PsychosocialDocumentVersion $released, array $validationBlock, array $validationRow): PsychosocialDocumentVersion
    {
        $type = $released->document->type;
        $this->config($type);

        $validated = DB::transaction(function () use ($released, $validationBlock, $validationRow): PsychosocialDocumentVersion {
            $document = PsychosocialDocument::query()->lockForUpdate()->findOrFail($released->document_id);
            $released = PsychosocialDocumentVersion::query()->findOrFail($released->id);

            if (! $this->isValidatable($released)) {
                throw new PsychosocialDocumentException('Esta versão não pode ser validada: só uma versão liberada e ainda sem validação aceita validação.');
            }

            $versionNumber = $this->nextVersionNumber($document);
            $survey = $document->survey;

            $validated = PsychosocialDocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'public_identifier' => $this->publicIdentifier($survey, $document->type, $versionNumber),
                'kind' => PsychosocialDocumentVersion::KIND_VALIDATED,
                'parent_version_id' => $released->id,
                'method_version_id' => $released->method_version_id,
                'instrument_version_id' => $released->instrument_version_id,
                'snapshot_schema' => $released->snapshot_schema,
                'snapshot' => $released->snapshot,
                'snapshot_sha256' => $released->snapshot_sha256,
                'validation_block' => $validationBlock,
                'content_sha256' => self::contentHash($released->snapshot, $validationBlock),
                'seal' => PsychosocialDocumentVersion::SEAL_VALIDATED,
                'pdf_status' => PsychosocialDocumentVersion::PDF_QUEUED,
            ]);

            PsychosocialDocumentValidation::query()->create(array_merge($validationRow, ['version_id' => $validated->id]));

            foreach ($released->attachments as $attachment) {
                PsychosocialDocumentAttachment::query()->create([
                    'version_id' => $validated->id,
                    'kind' => $attachment->kind,
                    'file_path' => $attachment->file_path,
                    'file_name' => $attachment->file_name,
                    'sha256' => $attachment->sha256,
                    'uploaded_by' => $attachment->uploaded_by,
                ]);
            }

            $document->update(['current_version_id' => $validated->id]);

            return $validated;
        });

        $this->printPdf($validated, $type);

        return $validated->fresh();
    }

    private function printPdf(PsychosocialDocumentVersion $version, string $type): void
    {
        $path = sprintf(
            'psychosocial-documents/%d/%s/v%d-%s.pdf',
            $version->document->survey_id,
            $type,
            $version->version_number,
            $version->public_identifier,
        );
        $disk = Storage::disk(self::DISK);

        try {
            $pdf = Pdf::loadView($this->config($type)['view'], [
                'snapshot' => $version->snapshot,
                'validation' => $version->validation_block,
                // Identificação da versão no rodapé; não entra no hash do conteúdo (que é só o snapshot).
                'meta' => [
                    'version_number' => $version->version_number,
                    'public_identifier' => $version->public_identifier,
                ],
            ])->setPaper('a4');
            $bytes = $this->reportGenerator->applyDompdfOptions($pdf)->output();

            if (! $disk->put($path, $bytes)) {
                throw new PsychosocialDocumentException('Não foi possível gravar o arquivo do PDF.');
            }

            $version->update([
                'pdf_status' => PsychosocialDocumentVersion::PDF_READY,
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $bytes),
                'pdf_error' => null,
            ]);
        } catch (Throwable $exception) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            Log::warning('Falha ao gerar o PDF de documento psicossocial.', [
                'version_id' => $version->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            $version->update([
                'pdf_status' => PsychosocialDocumentVersion::PDF_FAILED,
                'pdf_path' => null,
                'pdf_sha256' => null,
                'pdf_error' => Str::limit($exception->getMessage(), 1000),
            ]);
        }
    }

    private function lockedDocument(Survey $survey, string $type): PsychosocialDocument
    {
        $document = PsychosocialDocument::query()->firstOrCreate(
            ['survey_id' => $survey->id, 'type' => $type],
            ['company_id' => $survey->company_id],
        );

        return PsychosocialDocument::query()->lockForUpdate()->findOrFail($document->id);
    }

    private function nextVersionNumber(PsychosocialDocument $document): int
    {
        return (int) $document->versions()->max('version_number') + 1;
    }

    private function publicIdentifier(Survey $survey, string $type, int $versionNumber): string
    {
        return sprintf('%s-%d-%d-%s', $this->config($type)['prefix'], $survey->id, $versionNumber, Str::upper(Str::random(8)));
    }

    private function builder(string $type): DocumentSnapshotBuilder
    {
        return app($this->config($type)['builder']);
    }

    /**
     * @return array{builder: class-string<DocumentSnapshotBuilder>, view: string, prefix: string, file: string, label: string}
     */
    private function config(string $type): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new PsychosocialDocumentException("Tipo de documento não suportado nesta fase: «{$type}».");
        }

        return self::TYPES[$type];
    }
}
