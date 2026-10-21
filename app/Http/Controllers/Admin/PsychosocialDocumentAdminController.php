<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialAccessLog;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentAttachment;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Services\Psychosocial\PsychosocialDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Talents: libera o subsídio AEP/PGR, baixa os documentos e registra a validação informada.
 * O Admin Talents não valida no sistema.
 */
class PsychosocialDocumentAdminController extends Controller
{
    public function __construct(private readonly PsychosocialDocumentService $documents)
    {
    }

    public function releaseSubsidy(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        try {
            $version = $this->documents->release($survey, PsychosocialDocument::TYPE_PGR_SUBSIDY);
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $version->pdf_status === PsychosocialDocumentVersion::PDF_READY
            ? $this->backToPlan($company, $survey)->with('success', "Subsídio AEP/PGR liberado (versão {$version->version_number}).")
            : $this->backToPlan($company, $survey)->with('error', "A versão {$version->version_number} foi registrada, mas o PDF não foi gerado: {$version->pdf_error}");
    }

    public function releaseDossier(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.kind' => ['required', 'string', 'in:'.implode(',', PsychosocialDocumentAttachment::KINDS)],
            'attachments.*.file' => ['required', 'file', 'max:20480'],
        ], [
            'attachments.*.file.required' => 'Selecione o arquivo do anexo ou remova a linha.',
            'attachments.*.file.max' => 'Cada anexo pode ter no máximo 20 MB.',
        ]);

        $uploads = [];
        foreach ($data['attachments'] ?? [] as $row) {
            $uploads[] = [
                'kind' => $row['kind'],
                'file' => $row['file'],
            ];
        }

        try {
            $version = $this->documents->releaseDossier($survey, $request->user(), $uploads);
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $version->pdf_status === PsychosocialDocumentVersion::PDF_READY
            ? $this->backToPlan($company, $survey)->with('success', "Dossiê de evidências liberado (versão {$version->version_number}).")
            : $this->backToPlan($company, $survey)->with('error', "A versão {$version->version_number} foi registrada, mas o PDF não foi gerado: {$version->pdf_error}");
    }

    public function downloadAttachment(Request $request, Company $company, Survey $survey, PsychosocialDocumentAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $version = $attachment->version;
        $document = $version?->document;
        abort_unless(
            $document
            && (int) $document->survey_id === (int) $survey->id
            && (int) $document->company_id === (int) $company->id,
            404
        );

        $disk = Storage::disk(PsychosocialDocumentService::DISK);
        if (! $disk->exists($attachment->file_path)) {
            return $this->backToPlan($company, $survey)->with('error', 'O anexo não foi encontrado.');
        }

        $bytes = $disk->get($attachment->file_path);
        if (! hash_equals($attachment->sha256, hash('sha256', (string) $bytes))) {
            return $this->backToPlan($company, $survey)->with('error', 'O anexo não confere com o hash registrado.');
        }

        PsychosocialAccessLog::query()->create([
            'user_id' => $request->user()->id,
            'company_id' => $company->id,
            'survey_id' => $survey->id,
            'resource' => 'document_download',
            'document_version_id' => $version->id,
            'ip' => $request->ip(),
        ]);

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $attachment->file_name);
    }

    public function download(Request $request, Company $company, Survey $survey, string $type): SymfonyResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $version = $this->documents->currentVersion($survey, $type);
        abort_if($version === null, 404);

        try {
            return $this->documents->download($version, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }
    }

    public function validateInformed(Request $request, Company $company, Survey $survey, string $type): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'registry' => ['nullable', 'string', 'max:64'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'proof.required' => 'Anexe o comprovante da validação.',
        ]);

        $version = $this->documents->currentVersion($survey, $type);
        abort_if($version === null, 404);

        try {
            $validated = $this->documents->validateInformed($version, $request->user(), [
                'name' => trim($data['name']),
                'role' => trim($data['role']),
                'registry' => filled($data['registry'] ?? null) ? trim($data['registry']) : null,
                'date' => $data['date'],
            ], $request->file('proof'));
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $this->backToPlan($company, $survey)
            ->with('success', PsychosocialDocumentService::label($type)." validado (versão {$validated->version_number}).");
    }

    private function backToPlan(Company $company, Survey $survey): RedirectResponse
    {
        return redirect()->route('admin.companies.surveys.action-plan.edit', [$company->id, $survey->id]);
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
