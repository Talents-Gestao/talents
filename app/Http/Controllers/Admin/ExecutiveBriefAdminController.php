<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Services\ExecutiveBriefService;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Support\Nr1Shell\Nr1Shell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExecutiveBriefAdminController extends Controller
{
    public function __construct(
        private readonly ExecutiveBriefService $service,
        private readonly PsychosocialDocumentService $documents,
    ) {}

    public function release(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return redirect(Nr1Shell::parecerUrl($survey))
                ->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        $version = $this->service->release($survey);

        $redirect = redirect(Nr1Shell::parecerUrl($survey));

        return $version->pdf_status === PsychosocialDocumentVersion::PDF_READY
            ? $redirect->with('success', "Resumo executivo liberado (versão {$version->version_number}).")
            : $redirect->with('error', "A versão {$version->version_number} foi registrada, mas o PDF não foi gerado: {$version->pdf_error}");
    }

    public function download(Request $request, Company $company, Survey $survey): SymfonyResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $version = $this->service->currentVersion($survey);
        abort_if($version === null, 404);

        try {
            return $this->service->download($version, $request->user(), $request->ip());
        } catch (PsychosocialDocumentException $exception) {
            return redirect(Nr1Shell::parecerUrl($survey))->with('error', $exception->getMessage());
        }
    }

    /**
     * Validação in-system pela Talents no modal do parecer legado (sem anexo).
     */
    public function validateInSystem(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        if ($survey->usesReviewFlow()) {
            return redirect(Nr1Shell::parecerUrl($survey))
                ->with('error', 'Nesta campanha os documentos seguem o fluxo de revisão, em Documentos.');
        }

        $request->validate([
            'declaration_accepted' => ['accepted'],
        ], [
            'declaration_accepted.accepted' => 'Confirme que revisou o resumo executivo para validar.',
        ]);

        $version = $this->service->currentVersion($survey);
        abort_if($version === null, 404);

        try {
            $validated = $this->documents->validateInSystem($version, $request->user(), true);
        } catch (PsychosocialDocumentException $exception) {
            return redirect(Nr1Shell::parecerUrl($survey))->with('error', $exception->getMessage());
        }

        return redirect(Nr1Shell::parecerUrl($survey))
            ->with('success', "Resumo executivo validado (versão {$validated->version_number}).");
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
