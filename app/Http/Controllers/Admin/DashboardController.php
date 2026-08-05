<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\AdminHomeDashboardBuilder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(AdminHomeDashboardBuilder $builder): Response
    {
        $home = $builder->build();

        return Inertia::render('Admin/Dashboard', [
            'finance' => $home['finance'],
            'commercial' => $home['commercial'],
            'operationToday' => $home['operation_today'],
            'alertsCount' => $home['alerts_count'],
            'adminTasksOpen' => $home['admin_tasks_open'],
            'kpis' => $home['kpis'],
            'leadsBySource' => $home['leads_by_source'],
            'funnel' => $home['funnel'],
            'monthlyGoal' => $home['monthly_goal'],
        ]);
    }
}
