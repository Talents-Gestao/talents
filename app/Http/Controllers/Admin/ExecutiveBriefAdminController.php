<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialDocumentVersion;
use App\Models\Survey;
use App\Services\ExecutiveBriefService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExecutiveBriefAdminController extends Controller
{
    public function __construct(private readonly ExecutiveBriefService $service)
    {
    }

    public function release(Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $version = $this->service->release($survey);

        $redirect = redirect()->route('admin.companies.surveys.action-plan.edit', [$company->id, $survey->id]);

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
            return redirect()
                ->route('admin.companies.surveys.action-plan.edit', [$company->id, $survey->id])
                ->with('error', $exception->getMessage());
        }
    }

    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
    }
}
