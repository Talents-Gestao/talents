<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActionPlanItem;
use App\Models\Survey;
use App\Support\TechnicalOpinionDisclaimerStripper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActionPlanController extends Controller
{
    private function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    private function findSurvey(Request $request, Survey $survey): Survey
    {
        abort_unless($survey->company_id === $this->companyId($request), 404);

        return $survey;
    }

    public function show(Request $request, Survey $survey): Response
    {
        $survey = $this->findSurvey($request, $survey);

        $plan = $survey->actionPlans()->latest()->first();

        $hasOpinion = $plan !== null
            && filled($plan->technical_opinion)
            && trim(strip_tags((string) $plan->technical_opinion)) !== '';

        $hasOpinionFile = $plan !== null && filled($plan->technical_opinion_file_path);

        $visible = $plan !== null
            && $plan->admin_published_at !== null
            && ($hasOpinion || $hasOpinionFile);

        return Inertia::render('Client/Surveys/ActionPlan', [
            'survey' => $survey,
            'plan' => $visible ? [
                'id' => $plan->id,
                'technical_opinion' => TechnicalOpinionDisclaimerStripper::strip($plan->technical_opinion),
                'technical_opinion_file_name' => $plan->technical_opinion_file_name,
                'technical_opinion_file_url' => $hasOpinionFile
                    ? route('client.surveys.action-plan.technical-opinion-file', $survey)
                    : null,
            ] : null,
            'actionPlanLocked' => ! $visible,
        ]);
    }

    public function downloadTechnicalOpinionFile(Request $request, Survey $survey): StreamedResponse
    {
        $survey = $this->findSurvey($request, $survey);

        $plan = $survey->actionPlans()->latest()->first();

        abort_unless(
            $plan
            && $plan->admin_published_at !== null
            && filled($plan->technical_opinion_file_path)
            && Storage::disk('local')->exists($plan->technical_opinion_file_path),
            404
        );

        return Storage::disk('local')->download(
            $plan->technical_opinion_file_path,
            $plan->technical_opinion_file_name ?? 'parecer-tecnico'
        );
    }

    public function updateItem(Request $request, ActionPlanItem $item): RedirectResponse
    {
        $plan = $item->actionPlan;
        abort_unless($plan->company_id === $this->companyId($request), 403);

        $data = $request->validate([
            'responsible_name' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:pending,in_progress,done'],
        ]);

        $item->update($data);

        return back()->with('success', 'Item atualizado.');
    }
}
