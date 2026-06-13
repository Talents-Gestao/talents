<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StrategicCalendarItemKind;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\LandingInterestSubmission;
use App\Models\StrategicCalendarItem;
use App\Support\StrategicCalendarOccurrenceExpander;
use App\Models\Subscription;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $companiesCount = Company::query()->count();
        $activeCompanies = Company::query()->where('is_active', true)->count();

        $surveysTotal = Survey::query()->count();
        $companiesWithActiveCampaign = Survey::query()
            ->where('status', 'active')
            ->pluck('company_id')
            ->unique()
            ->count();

        $responsesCompleted = SurveyResponse::query()->whereNotNull('completed_at')->count();
        $responsesTotal = SurveyResponse::query()->count();
        $completionRate = $responsesTotal > 0
            ? round(100 * $responsesCompleted / $responsesTotal, 1)
            : 0.0;

        $riskBySegment = SurveyResult::query()
            ->whereNull('survey_template_section_id')
            ->whereNull('department_id')
            ->join('surveys', 'surveys.id', '=', 'survey_results.survey_id')
            ->join('companies', 'companies.id', '=', 'surveys.company_id')
            ->whereNotNull('companies.segment')
            ->select('companies.segment', DB::raw('avg(survey_results.average_score) as avg_score'))
            ->groupBy('companies.segment')
            ->orderByDesc('avg_score')
            ->get();

        $lastSurveyPerCompany = Survey::query()
            ->select('company_id', DB::raw('MAX(id) as survey_id'))
            ->groupBy('company_id');

        $criticalCompanies = DB::table('companies')
            ->joinSub($lastSurveyPerCompany, 'last_s', function ($join) {
                $join->on('last_s.company_id', '=', 'companies.id');
            })
            ->join('survey_results', function ($join) {
                $join->on('survey_results.survey_id', '=', 'last_s.survey_id')
                    ->whereNull('survey_results.survey_template_section_id')
                    ->whereNull('survey_results.department_id');
            })
            ->where('companies.is_active', true)
            ->where('survey_results.risk_level', 'red')
            ->orderBy('companies.name')
            ->limit(10)
            ->get(['companies.id', 'companies.name', 'companies.segment', 'survey_results.risk_level', 'survey_results.average_score'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'segment' => $row->segment,
                'risk_level' => $row->risk_level,
                'average_score' => (float) $row->average_score,
            ]);

        $riskDistribution = SurveyResult::query()
            ->joinSub($lastSurveyPerCompany, 'last_s', function ($join) {
                $join->on('survey_results.survey_id', '=', 'last_s.survey_id');
            })
            ->whereNull('survey_results.survey_template_section_id')
            ->whereNull('survey_results.department_id')
            ->select('survey_results.risk_level', DB::raw('count(*) as c'))
            ->groupBy('survey_results.risk_level')
            ->pluck('c', 'risk_level')
            ->all();

        $pendingComplaintRows = Complaint::query()
            ->whereIn('status', ['new', 'under_review'])
            ->select('company_id', DB::raw('count(*) as c'))
            ->groupBy('company_id')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $pendingCompanyIds = $pendingComplaintRows->pluck('company_id')->filter()->unique()->values();
        $pendingCompanyNames = Company::query()
            ->whereIn('id', $pendingCompanyIds)
            ->pluck('name', 'id');
        $pendingComplaints = $pendingComplaintRows->map(fn ($row) => [
            'company_id' => (int) $row->company_id,
            'company_name' => $pendingCompanyNames[(int) $row->company_id] ?? '—',
            'count' => (int) $row->c,
        ]);

        $recentLeads = LandingInterestSubmission::query()
            ->whereNull('mail_sent_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'name', 'email', 'phone', 'company', 'message', 'mail_sent_at', 'created_at']);

        $today = Carbon::today()->startOfDay();
        $weekEnd = $today->copy()->addDays(7)->endOfDay();
        $masters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            StrategicCalendarItem::query()->with(['company:id,name', 'attachments']),
            $today,
            $weekEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $upcomingCalendar = StrategicCalendarOccurrenceExpander::expandCollection(
            $masters,
            $today,
            $weekEnd,
        )->take(5)->values();

        $subscriptionsDueSoon = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [Carbon::today()->toDateString(), Carbon::today()->addDays(30)->toDateString()])
            ->with('company:id,name')
            ->orderBy('ends_at')
            ->limit(3)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'company_id' => $s->company_id,
                'company_name' => $s->company?->name ?? '—',
                'ends_at' => $s->ends_at?->toDateString(),
            ]);

        $responsesLast30d = $this->dailyCountsLastDays(
            SurveyResponse::query()->whereNotNull('completed_at'),
            'completed_at',
            30
        );

        $complaintsLast30d = $this->dailyCountsLastDays(
            Complaint::query()->whereIn('status', ['new', 'under_review']),
            'created_at',
            30
        );

        $pendingComplaintsTotal = Complaint::query()
            ->whereIn('status', ['new', 'under_review'])
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'companies_total' => $companiesCount,
                'companies_active' => $activeCompanies,
                'companies_with_active_campaign' => $companiesWithActiveCampaign,
                'surveys_total' => $surveysTotal,
                'responses_completed' => $responsesCompleted,
                'responses_total' => $responsesTotal,
                'completion_rate' => $completionRate,
                'pending_complaints_total' => $pendingComplaintsTotal,
                'responses_sparkline' => $responsesLast30d,
                'complaints_sparkline' => $complaintsLast30d,
            ],
            'riskBySegment' => $riskBySegment,
            'riskDistribution' => [
                'green' => (int) ($riskDistribution['green'] ?? 0),
                'yellow' => (int) ($riskDistribution['yellow'] ?? 0),
                'red' => (int) ($riskDistribution['red'] ?? 0),
            ],
            'criticalCompanies' => $criticalCompanies,
            'pendingComplaints' => $pendingComplaints,
            'recentLeads' => $recentLeads,
            'upcomingCalendar' => $upcomingCalendar,
            'subscriptionsDueSoon' => $subscriptionsDueSoon,
            'calendarKindLabels' => collect(StrategicCalendarItemKind::cases())
                ->mapWithKeys(fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()])
                ->all(),
        ]);
    }

    /**
     * @param  Builder  $query
     * @return array<int, array{date: string, count: int}>
     */
    private function dailyCountsLastDays($query, string $dateColumn, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1)->startOfDay();
        $counts = (clone $query)
            ->where($dateColumn, '>=', $start)
            ->pluck($dateColumn)
            ->countBy(fn ($dt) => Carbon::parse($dt)->toDateString());

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->toDateString();
            $out[] = ['date' => $d, 'count' => (int) ($counts[$d] ?? 0)];
        }

        return $out;
    }
}
