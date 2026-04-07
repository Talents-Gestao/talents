<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MethodologyFormTemplate;
use App\Models\Module;
use Inertia\Inertia;
use Inertia\Response;

class MethodologyController extends Controller
{
    public function index(): Response
    {
        $companiesWithMethodology = Company::query()
            ->whereHas('subscriptions', function ($q) {
                $q->where('status', 'active')
                    ->whereHas('plan.modules', fn ($mq) => $mq->where('modules.key', Module::KEY_METODOLOGIA));
            })
            ->count();

        $templatesCount = MethodologyFormTemplate::query()->count();

        $recentTemplates = MethodologyFormTemplate::query()
            ->withCount(['sections', 'companies'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Methodology/Index', [
            'stats' => [
                'companies_with_methodology' => $companiesWithMethodology,
                'templates_count' => $templatesCount,
            ],
            'recentTemplates' => $recentTemplates,
        ]);
    }
}
