<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Services\Nr1ReportService;
use App\Services\ReportGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    public function executive(Request $request, Survey $survey, Nr1ReportService $reports): Response
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $reports->streamExecutive($survey);
    }

    public function technical(Request $request, Survey $survey, ReportGenerator $generator): Response
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $generator->technicalPdf($survey)->stream('relatorio-tecnico-'.$survey->id.'.pdf');
    }

    public function referral(Request $request, Survey $survey, Nr1ReportService $reports): Response
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $reports->streamTechnicalReferral($survey);
    }

    public function actionPlan(Request $request, Survey $survey, Nr1ReportService $reports): Response
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $reports->streamActionPlan($survey);
    }
}
