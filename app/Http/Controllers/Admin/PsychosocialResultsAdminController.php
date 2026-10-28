<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PsychosocialInternalReview;
use App\Models\Survey;
use App\Services\Psychosocial\InternalReviewService;
use App\Services\PsychosocialDashboardBuilder;
use App\Services\PsychosocialDashboardExtras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel de resultados da campanha V11 para a consultoria: o mesmo painel da empresa, antes e depois de "Publicado",
 * mais a "Revisão interna da Talents" (Caderno §9), que nenhuma outra tela recebe.
 */
class PsychosocialResultsAdminController extends Controller
{
    public function __construct(
        private readonly PsychosocialDashboardBuilder $builder,
        private readonly PsychosocialDashboardExtras $extras,
        private readonly InternalReviewService $reviews,
    ) {}

    public function show(Company $company, Survey $survey): Response
    {
        $this->authorizeSurvey($company, $survey);

        $dashboard = $this->builder->build($survey);
        $items = $this->reviews->items($dashboard['attention']);

        return Inertia::render('Admin/Surveys/PsychosocialResults', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'dashboard' => $dashboard,
            'nextActions' => $this->extras->nextActions($survey),
            'campaigns' => $this->extras->campaigns($survey, false, fn (Survey $s) => route('admin.companies.surveys.results.show', [$company->id, $s->id])),
            'links' => [
                'plan' => route('admin.companies.surveys.measures.edit', [$company->id, $survey->id]),
                'documents' => route('admin.companies.surveys.documents.index', [$company->id, $survey->id]),
                'campaign' => route('admin.companies.surveys.campaign.show', [$company->id, $survey->id]),
            ],
            'published' => $survey->published_at?->format('d/m/Y H:i'),
            'internalReview' => [
                'items' => $items,
                'decisions' => PsychosocialInternalReview::DECISION_LABELS,
                'save_url' => route('admin.companies.surveys.internal-review.store', [$company->id, $survey->id]),
                ...$this->reviews->state($survey),
            ],
        ]);
    }

    public function storeInternalReview(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->authorizeSurvey($company, $survey);

        $data = $request->validate([
            'decision' => ['required', Rule::in(array_keys(PsychosocialInternalReview::DECISION_LABELS))],
            'note' => ['nullable', 'string', 'max:5000'],
            'checked_items' => ['nullable', 'array'],
            'checked_items.*' => ['string', 'max:60'],
        ]);

        $items = $this->reviews->items($this->builder->build($survey)['attention']);

        try {
            $this->reviews->save($survey, $request->user(), $data['decision'], $data['note'] ?? null, $data['checked_items'] ?? [], $items);
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Análise interna salva.');
    }

    private function authorizeSurvey(Company $company, Survey $survey): void
    {
        abort_unless((int) $survey->company_id === (int) $company->id, 404);
        abort_unless($survey->usesReviewFlow(), 404);
    }
}
