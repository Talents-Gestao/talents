<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StrategicCalendarViewPeriod;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()->with('modules')->orderBy('name')->paginate(20);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Create', [
            'modules' => Module::query()->orderBy('name')->get(),
            'strategicCalendarViewPeriodOptions' => $this->strategicCalendarViewPeriodOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'strategic_calendar_view_period' => $request->input('strategic_calendar_view_period') ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_monthly_cents' => ['required', 'integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:0'],
            'max_surveys_per_year' => ['nullable', 'integer', 'min:0'],
            'module_ids' => ['array'],
            'module_ids.*' => ['exists:modules,id'],
            'strategic_calendar_view_period' => ['nullable', Rule::in(StrategicCalendarViewPeriod::values())],
            'is_active' => ['boolean'],
        ]);

        $plan = Plan::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name'].'-'.uniqid()),
            'price_monthly_cents' => $data['price_monthly_cents'],
            'max_employees' => $data['max_employees'] ?? null,
            'max_surveys_per_year' => $data['max_surveys_per_year'] ?? null,
            'strategic_calendar_view_period' => $data['strategic_calendar_view_period'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $plan->modules()->sync($data['module_ids'] ?? []);

        return redirect()->route('admin.plans.index')->with('success', 'Plano criado.');
    }

    public function edit(Plan $plan): Response
    {
        $plan->load('modules');

        return Inertia::render('Admin/Plans/Edit', [
            'plan' => $plan,
            'modules' => Module::query()->orderBy('name')->get(),
            'strategicCalendarViewPeriodOptions' => $this->strategicCalendarViewPeriodOptions(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $request->merge([
            'strategic_calendar_view_period' => $request->input('strategic_calendar_view_period') ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_monthly_cents' => ['required', 'integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:0'],
            'max_surveys_per_year' => ['nullable', 'integer', 'min:0'],
            'module_ids' => ['array'],
            'module_ids.*' => ['exists:modules,id'],
            'strategic_calendar_view_period' => ['nullable', Rule::in(StrategicCalendarViewPeriod::values())],
            'is_active' => ['boolean'],
        ]);

        $plan->update([
            'name' => $data['name'],
            'price_monthly_cents' => $data['price_monthly_cents'],
            'max_employees' => $data['max_employees'] ?? null,
            'max_surveys_per_year' => $data['max_surveys_per_year'] ?? null,
            'strategic_calendar_view_period' => $data['strategic_calendar_view_period'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $plan->modules()->sync($data['module_ids'] ?? []);

        return redirect()->route('admin.plans.index')->with('success', 'Plano atualizado.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Plano removido.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function strategicCalendarViewPeriodOptions(): array
    {
        return collect(StrategicCalendarViewPeriod::cases())
            ->map(fn (StrategicCalendarViewPeriod $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ])
            ->values()
            ->all();
    }
}
