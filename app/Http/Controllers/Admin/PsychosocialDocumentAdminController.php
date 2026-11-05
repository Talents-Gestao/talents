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
use App\Support\Nr1Shell\Nr1Shell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Talents: libera o subsídio AEP/PGR e o relatório técnico (parecer legado),
 * baixa os documentos e registra validação informada ou in-system do relatório técnico.
 */
class PsychosocialDocumentAdminController extends Controller
{
    public function __construct(private readonly PsychosocialDocumentService $documents) {}

    public function releaseSubsidy(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        try {
            $version = $this->documents->release($survey, PsychosocialDocument::TYPE_PGR_SUBSIDY);
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $version->pdf_status === PsychosocialDocumentVersion::PDF_READY
            ? $this->backToPlan($company, $survey)->with('success', "Subsídio AEP/PGR publicado no painel da empresa (versão {$version->version_number}).")
            : $this->backToPlan($company, $survey)->with('error', "A versão {$version->version_number} foi registrada, mas o PDF não foi gerado: {$version->pdf_error}");
    }

    /**
     * Liberação do relatório técnico + plano de ação no parecer legado (substitui o resumo executivo na UI).
     */
    public function releaseTechnicalReport(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        try {
            $version = $this->documents->release($survey, PsychosocialDocument::TYPE_TECHNICAL_REPORT);
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $version->pdf_status === PsychosocialDocumentVersion::PDF_READY
            ? $this->backToPlan($company, $survey)->with('success', "Relatório técnico publicado no painel da empresa (versão {$version->version_number}).")
            : $this->backToPlan($company, $survey)->with('error', "A versão {$version->version_number} foi registrada, mas o PDF não foi gerado: {$version->pdf_error}");
    }

    /**
     * Nova versão só dos documentos que já estão no painel da empresa.
     * A Mia não dispara isto: a Talents pede depois de revisar o parecer.
     */
    public function replacePublished(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        $updated = [];
        $errors = [];

        foreach ([PsychosocialDocument::TYPE_TECHNICAL_REPORT, PsychosocialDocument::TYPE_PGR_SUBSIDY] as $type) {
            $current = $this->documents->currentVersion($survey, $type);
            if ($current === null || $current->pdf_status !== PsychosocialDocumentVersion::PDF_READY) {
                continue;
            }

            try {
                $version = $this->documents->release($survey, $type);
            } catch (PsychosocialDocumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            if ($version->pdf_status === PsychosocialDocumentVersion::PDF_READY) {
                $updated[] = PsychosocialDocumentService::label($type).' (versão '.$version->version_number.')';
            } else {
                $errors[] = PsychosocialDocumentService::label($type).': '.$version->pdf_error;
            }
        }

        if ($updated === [] && $errors === []) {
            return $this->backToPlan($company, $survey)->with('error', 'Nenhum arquivo publicado para atualizar.');
        }

        if ($updated === []) {
            return $this->backToPlan($company, $survey)->with('error', implode(' ', $errors));
        }

        $message = 'Arquivos atualizados no painel da empresa: '.implode(', ', $updated).'.';
        if ($errors !== []) {
            $message .= ' '.implode(' ', $errors);
        }

        return $this->backToPlan($company, $survey)->with('success', $message);
    }

    /**
     * Validação in-system pela Talents no modal do parecer legado.
     */
    public function validateTechnicalReportInSystem(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        $request->validate([
            'declaration_accepted' => ['accepted'],
        ], [
            'declaration_accepted.accepted' => 'Confirme que revisou o relatório técnico para validar.',
        ]);

        $version = $this->documents->currentVersion($survey, PsychosocialDocument::TYPE_TECHNICAL_REPORT);
        abort_if($version === null, 404);

        try {
            $validated = $this->documents->validateInSystem($version, $request->user(), true);
        } catch (PsychosocialDocumentException $exception) {
            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }

        return $this->backToPlan($company, $survey)
            ->with('success', "Relatório técnico validado (versão {$validated->version_number}).");
    }

    public function releaseDossier(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

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

        $inline = $request->boolean('inline');

        try {
            return $this->documents->download($version, $request->user(), $request->ip(), $inline);
        } catch (PsychosocialDocumentException $exception) {
            if ($inline || $request->expectsJson()) {
                abort(422, $exception->getMessage());
            }

            return $this->backToPlan($company, $survey)->with('error', $exception->getMessage());
        }
    }

    public function preview(Company $company, Survey $survey, string $type): SymfonyResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        return $this->documents->preview($survey, $type);
    }

    public function validateInformed(Request $request, Company $company, Survey $survey, string $type): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return $this->backToPlan($company, $survey)->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

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
        return redirect(Nr1Shell::parecerUrl($survey));
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
