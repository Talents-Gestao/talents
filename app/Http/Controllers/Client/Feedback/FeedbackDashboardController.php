<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Models\CompanyEmployee;
use App\Models\FeedbackSession;
use App\Services\Feedback\FeedbackTeamAnalyticsService;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackDashboardController extends FeedbackCompanyController
{
    public function index(Request $request, FeedbackTeamAnalyticsService $analytics): Response
    {
        $context = app(FeedbackCompanyContext::class);
        $user = $request->user();

        if ($context->needsCompanySelection($request)) {
            return Inertia::render('Client/Feedbacks/Index', [
                'recentSessions' => [],
                'analytics' => $this->emptyAnalytics(),
                'employeeCount' => 0,
                'isCompanyAdmin' => true,
                'companyPicker' => $context->availableCompanies(),
                'activeCompany' => null,
                'isAdminContext' => true,
            ]);
        }

        $company = $this->company($request);

        $sessionsQuery = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->with(['employee', 'leader', 'template'])
            ->orderByDesc('id')
            ->limit(10);

        FeedbackVisibility::scopeSessions($sessionsQuery, $user);

        $employeesQuery = CompanyEmployee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true);

        FeedbackVisibility::scopeEmployees($employeesQuery, $user);

        return Inertia::render('Client/Feedbacks/Index', [
            'recentSessions' => $sessionsQuery->get(),
            'analytics' => $analytics->forCompany($company, $user),
            'employeeCount' => $employeesQuery->count(),
            'isCompanyAdmin' => FeedbackVisibility::actsAsCompanyAdmin($user),
            'companyPicker' => $context->isAdminContext($request) ? $context->availableCompanies() : null,
            'activeCompany' => $company->only(['id', 'name']),
            'isAdminContext' => $context->isAdminContext($request),
        ]);
    }

    /**
     * @return array{
     *   thermometer: array{labels: list<string>, series: list<int>},
     *   perceptions: array{labels: list<string>, series: list<int>},
     *   timeline: array{labels: list<string>, series: list<int>},
     *   strengths: list<string>,
     *   weaknesses: list<string>,
     *   completed_count: int,
     * }
     */
    private function emptyAnalytics(): array
    {
        return [
            'thermometer' => ['labels' => [], 'series' => []],
            'perceptions' => ['labels' => [], 'series' => []],
            'timeline' => ['labels' => [], 'series' => []],
            'strengths' => [],
            'weaknesses' => [],
            'completed_count' => 0,
        ];
    }
}
