<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDashboardSettings;
use App\Support\Admin\AdminHomeDashboardBuilder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminHomeDashboardBuilder $builder): Response
    {
        $range = $request->validate([
            'data_inicio' => ['nullable', 'required_with:data_fim', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'required_with:data_inicio', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
        ], [
            'data_inicio.required_with' => 'Informe a data inicial.',
            'data_inicio.date_format' => 'Data inicial inválida.',
            'data_fim.required_with' => 'Informe a data final.',
            'data_fim.date_format' => 'Data final inválida.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ]);

        $home = $builder->build($request->user());

        // Filtro opcional do card «Funil comercial»: só entra no payload quando o período é informado.
        $funnelRangeProps = [];
        if (! empty($range['data_inicio']) && ! empty($range['data_fim'])) {
            $start = Carbon::createFromFormat('Y-m-d', $range['data_inicio'])->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $range['data_fim'])->startOfDay();
            $funnelRange = $builder->funnelForRange($start, $end);

            $funnelRangeProps = [
                'funnelRange' => $funnelRange['stages'],
                'funnelLostRange' => $funnelRange['lost'],
                'funnelRangePeriod' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            ...$funnelRangeProps,
            'finance' => $home['finance'],
            'operationToday' => $home['operation_today'],
            'tasksToday' => $home['tasks_today'],
            'adminTasksOpen' => $home['admin_tasks_open'],
            'kpis' => $home['kpis'],
            'leadsBySource' => $home['leads_by_source'],
            'leadsThisMonth' => $home['leads_this_month'],
            'funnel' => $home['funnel'],
            'funnelLost' => $home['funnel_lost'],
            'funnelAll' => $home['funnel_all'],
            'funnelLostAll' => $home['funnel_lost_all'],
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
