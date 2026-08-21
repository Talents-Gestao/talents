<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDashboardSettings;
use App\Support\Admin\AdminHomeDashboardBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminHomeDashboardBuilder $builder): Response
    {
        $home = $builder->build($request->user());

        return Inertia::render('Admin/Dashboard', [
            'finance' => $home['finance'],
            'operationToday' => $home['operation_today'],
            'tasksToday' => $home['tasks_today'],
            'adminTasksOpen' => $home['admin_tasks_open'],
            'kpis' => $home['kpis'],
            'leadsBySource' => $home['leads_by_source'],
            'leadsThisMonth' => $home['leads_this_month'],
            'funnel' => $home['funnel'],
            'monthlyGoal' => $home['monthly_goal'],
        ]);
    }

    public function updateMonthlyGoal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'goal_reais' => ['required', 'numeric', 'min:0.01'],
        ], [
            'goal_reais.required' => 'Informe a meta mensal.',
            'goal_reais.numeric' => 'A meta mensal deve ser um valor numérico.',
            'goal_reais.min' => 'A meta mensal deve ser maior que zero.',
        ]);

        $settings = AdminDashboardSettings::current();
        $settings->update([
            'monthly_revenue_goal_cents' => (int) round(((float) $data['goal_reais']) * 100),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Meta mensal atualizada.');
    }
}
