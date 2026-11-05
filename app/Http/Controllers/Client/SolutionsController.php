<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Exceptions\Nr1CampaignException;
use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyServiceSuggestion;
use App\Services\Nr1\Nr1SolutionService;
use App\Services\SurveyServiceSuggestionService;
use App\Support\Nr1ServiceCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Soluções Talents para a empresa: três cartões de serviço e recomendações por dimensão em atenção, só depois de
 * «Publicado» (a rota passa por survey.published). Ver a tela não cria nada; «Solicitar conversa» é um POST explícito.
 */
class SolutionsController extends Controller
{
    public function __construct(private readonly Nr1SolutionService $solutions) {}

    public function show(Request $request, Survey $survey): Response
    {
        $this->authorizeSurvey($request, $survey);

        return Inertia::render('Client/Surveys/Solutions', [
            'survey' => ['id' => $survey->id, 'title' => $survey->title],
            'cards' => Nr1ServiceCards::all(),
            'recommendations' => $this->solutions->recommendations($survey, forCompany: true),
            'requests' => $this->solutions->companyRequests($survey),
            'canRequest' => $request->user()->canAccess(PermissionModule::Pesquisas, PermissionAction::Edit),
            'serviceSuggestions' => app(SurveyServiceSuggestionService::class)->payload($survey, forTalents: false),
        ]);
    }

    public function requestSuggestedService(Request $request, Survey $survey, SurveyServiceSuggestion $suggestion): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->contextCompanyId(), 404);

        try {
            app(SurveyServiceSuggestionService::class)->requestConversation($survey, $suggestion, $request->user());
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Conversa solicitada. A Talents entrará em contato.');
    }

    public function requestConversation(Request $request, Survey $survey, int $match): RedirectResponse
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->solutions->requestConversation($survey, $request->user(), $match, $data['note'] ?? null);
        } catch (Nr1CampaignException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Conversa solicitada. A Talents entrará em contato.');
    }

    private function authorizeSurvey(Request $request, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);
        abort_unless($survey->usesReviewFlow(), 404);
    }
}
