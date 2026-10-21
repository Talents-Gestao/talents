<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PsychosocialDocumentException;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Models\User;
use App\Support\CanonicalJson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Liberação do resumo executivo: congela o snapshot numa versão released (pending_validation)
 * e imprime o PDF a partir dele. O download entrega só o arquivo gravado, conferido pelo hash.
 */
class ExecutiveBriefService
{
    public const DISK = 'local';

    public const VIEW = 'psychosocial.executive_brief';

    public function __construct(
        private readonly ExecutiveBriefSnapshotBuilder $snapshotBuilder,
        private readonly ReportGenerator $reportGenerator,
    ) {
    }

    public function release(Survey $survey): PsychosocialDocumentVersion
    {
        $snapshot = $this->snapshotBuilder->build($survey);

        $version = DB::transaction(function () use ($survey, $snapshot): PsychosocialDocumentVersion {
            $document = PsychosocialDocument::query()->firstOrCreate(
                ['survey_id' => $survey->id, 'type' => PsychosocialDocument::TYPE_EXECUTIVE_BRIEF],
                ['company_id' => $survey->company_id],
            );
            $document = PsychosocialDocument::query()->lockForUpdate()->findOrFail($document->id);

            $versionNumber = (int) $document->versions()->max('version_number') + 1;

            $version = PsychosocialDocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => $versionNumber,
                'public_identifier' => sprintf('RE-%d-%d-%s', $survey->id, $versionNumber, Str::upper(Str::random(8))),
                'kind' => PsychosocialDocumentVersion::KIND_RELEASED,
                'method_version_id' => $survey->method_version_id,
                'instrument_version_id' => $survey->instrument_version_id,
                'snapshot_schema' => ExecutiveBriefSnapshotBuilder::SCHEMA,
                'snapshot' => $snapshot,
                'snapshot_sha256' => CanonicalJson::sha256($snapshot),
                'content_sha256' => CanonicalJson::sha256(['snapshot' => $snapshot, 'validation_block' => null]),
                'seal' => PsychosocialDocumentVersion::SEAL_PENDING_VALIDATION,
                'pdf_status' => PsychosocialDocumentVersion::PDF_QUEUED,
            ]);

            $document->update(['current_version_id' => $version->id]);

            return $version;
        });

        $this->printPdf($version);

        return $version->fresh();
    }

    public function currentVersion(Survey $survey): ?PsychosocialDocumentVersion
    {
        return PsychosocialDocument::query()
            ->where('survey_id', $survey->id)
            ->where('type', PsychosocialDocument::TYPE_EXECUTIVE_BRIEF)
            ->first()
            ?->currentVersion;
    }

    /**
     * @return array{version_number: int, pdf_status: ?string, released_at: ?string}|null
     */
    public function currentVersionSummary(Survey $survey): ?array
    {
        $version = $this->currentVersion($survey);

        return $version ? [
            'version_number' => $version->version_number,
            'pdf_status' => $version->pdf_status,
            'released_at' => $version->created_at?->toIso8601String(),
        ] : null;
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
            'resumo-executivo-'.$version->public_identifier.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function renderHtml(array $snapshot): string
    {
        return view(self::VIEW, ['snapshot' => $snapshot])->render();
    }

    private function printPdf(PsychosocialDocumentVersion $version): void
    {
        $path = sprintf(
            'psychosocial-documents/%d/%s/v%d-%s.pdf',
            $version->document->survey_id,
            PsychosocialDocument::TYPE_EXECUTIVE_BRIEF,
            $version->version_number,
            $version->public_identifier,
        );
        $disk = Storage::disk(self::DISK);

        try {
            $bytes = $this->reportGenerator
                ->applyDompdfOptions(Pdf::loadView(self::VIEW, ['snapshot' => $version->snapshot])->setPaper('a4'))
                ->output();

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

            Log::warning('Falha ao gerar o PDF do resumo executivo.', [
                'version_id' => $version->id,
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
}
