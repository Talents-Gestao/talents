<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\Company;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ActionPlanAdminController extends Controller
{
    private function assertSurveyBelongsToCompany(Company $company, Survey $survey): void
    {
        abort_unless($survey->company_id === $company->id, 404);
    }

    public function edit(Company $company, Survey $survey): Response
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $survey->load('template');

        $plan = ActionPlan::query()
            ->where('company_id', $company->id)
            ->where('survey_id', $survey->id)
            ->with('items')
            ->first();

        $items = $plan?->items->map(fn (ActionPlanItem $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'description' => $i->description ?? '',
        ])->values()->all() ?? [];

        return Inertia::render('Admin/ActionPlan/Edit', [
            'company' => $company->only(['id', 'name']),
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'status' => $survey->status,
            ],
            'plan' => $plan ? [
                'id' => $plan->id,
                'admin_published_at' => $plan->admin_published_at?->format('d/m/Y H:i'),
            ] : null,
            'items' => $items,
        ]);
    }

    public function update(Request $request, Company $company, Survey $survey): RedirectResponse
    {
        $this->assertSurveyBelongsToCompany($company, $survey);

        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($company, $survey, $data) {
            $plan = ActionPlan::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'survey_id' => $survey->id,
                ],
                ['status' => 'open']
            );

            $plan->items()->delete();

            foreach ($data['items'] as $index => $row) {
                ActionPlanItem::create([
                    'action_plan_id' => $plan->id,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'status' => 'pending',
                    'sort_order' => $index,
                ]);
            }

            if (count($data['items']) > 0) {
                $plan->update(['admin_published_at' => now()]);
            } else {
                $plan->update(['admin_published_at' => null]);
            }
        });

        return redirect()
            ->route('admin.companies.surveys.action-plan.edit', [$company, $survey])
            ->with('success', 'Plano de ação atualizado e disponibilizado para a empresa quando houver itens.');
    }
}
