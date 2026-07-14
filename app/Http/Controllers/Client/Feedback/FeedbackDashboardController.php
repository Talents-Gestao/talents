<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\Feedback;

use App\Models\FeedbackSession;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Feedback\FeedbackVisibility;
use App\Support\Rhid\RhidPersonDirectory;
use App\Services\Feedback\FeedbackTeamAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackDashboardController extends FeedbackCompanyController
{
    public function index(Request $request, FeedbackTeamAnalyticsService $analytics, RhidPersonDirectory $directory): Response
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
                'rhidCollaboratorsHref' => null,
            ]);
        }

        $company = $this->company($request);

        $sessionsQuery = FeedbackSession::query()
            ->where('company_id', $company->id)
            ->with(['employee', 'leader', 'template'])
            ->orderByDesc('id')
            ->limit(10);

        FeedbackVisibility::scopeSessions($sessionsQuery, $user);

        $employeeCount = $directory->activePersons($company, $user)->count();
        $rhidHref = ($company->hasRhidEnabled() && $company->rhidConfigured() && ! $context->isAdminContext($request))
            ? route('client.rhid.compliance.index')
            : null;

        $recentSessions = $sessionsQuery->get()->map(function (FeedbackSession $session) {
            return [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status->value,
                'status_label' => $session->status->label(),
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'employee' => $session->collaboratorPayload(),
                'leader' => $session->leader?->only(['id', 'name', 'email']),
                'template' => $session->template?->only(['id', 'title']),
            ];
        });

        return Inertia::render('Client/Feedbacks/Index', [
            'recentSessions' => $recentSessions,
            'analytics' => $analytics->forCompany($company, $user),
            'employeeCount' => $employeeCount,
            'isCompanyAdmin' => FeedbackVisibility::actsAsCompanyAdmin($user),
            'companyPicker' => $context->isAdminContext($request) ? $context->availableCompanies() : null,
            'activeCompany' => $company->only(['id', 'name']),
            'isAdminContext' => $context->isAdminContext($request),
            'rhidCollaboratorsHref' => $rhidHref,
        ]);
    }

    /**
     * @return array{
     *   thermometer: array{labels: list<string>, series: list<int>},
     *   perceptions: array{labels: list<string>, series: list<int>},
     *   nine_box: null,
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
            'nine_box' => null,
            'timeline' => ['labels' => [], 'series' => []],
            'strengths' => [],
            'weaknesses' => [],
            'completed_count' => 0,
        ];
    }
}
