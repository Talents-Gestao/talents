<?php

namespace App\Http\Controllers\Client;

use App\Enums\StrategicCalendarItemKind;
use App\Http\Controllers\Controller;
use App\Models\ActionPlanItem;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\StrategicCalendarItem;
use App\Models\Survey;
use App\Support\StrategicCalendarOccurrenceExpander;
use App\Support\StrategicCalendarPeriod;
use App\Models\SurveyResult;
use App\Models\TaskCard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        $userId = (int) $request->user()->id;

        $activeSurveys = Survey::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();

        $lastSurvey = Survey::query()
            ->where('company_id', $companyId)
            ->with('template')
            ->orderByDesc('id')
            ->first();

        $overall = null;
        $completionRate = null;
        $sectionResults = [];
        if ($lastSurvey) {
            $overall = SurveyResult::query()
                ->where('survey_id', $lastSurvey->id)
                ->whereNull('survey_template_section_id')
                ->whereNull('department_id')
                ->first();

            $responsesTotal = $lastSurvey->responses()->count();
            $responsesDone = $lastSurvey->completedResponses()->count();
            $completionRate = $responsesTotal > 0 ? round(100 * $responsesDone / $responsesTotal, 1) : null;

            $sectionResults = SurveyResult::query()
                ->where('survey_id', $lastSurvey->id)
                ->whereNotNull('survey_template_section_id')
                ->whereNull('department_id')
                ->join('survey_template_sections', 'survey_template_sections.id', '=', 'survey_results.survey_template_section_id')
                ->orderByDesc('survey_results.average_score')
                ->limit(3)
                ->get([
                    'survey_template_sections.title as section_name',
                    'survey_results.average_score',
                    'survey_results.risk_level',
                ])
                ->map(fn ($row) => [
                    'section_name' => $row->section_name,
                    'average_score' => (float) $row->average_score,
                    'risk_level' => $row->risk_level,
                ])
                ->all();
        }

        $company = Company::query()->find($companyId);
        $complaintsPublicUrl = $company?->complaints_public_token
            ? url('/denuncia/'.$company->complaints_public_token)
            : null;

        $pendingComplaintsCount = Complaint::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['new', 'under_review'])
            ->count();

        $openActionPlanCount = ActionPlanItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('actionPlan', fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $openActionPlanItems = ActionPlanItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('actionPlan', fn ($q) => $q->where('company_id', $companyId))
            ->with(['actionPlan' => fn ($q) => $q->select('id', 'survey_id', 'company_id')->with('survey:id,title')])
            ->orderByRaw("CASE WHEN action_plan_items.status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'title', 'status', 'due_date', 'action_plan_id'])
            ->map(fn (ActionPlanItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'status' => $item->status,
                'due_date' => $item->due_date?->toDateString(),
                'survey_title' => $item->actionPlan?->survey?->title,
            ]);

        $pendingTasks = TaskCard::query()
            ->where('company_id', $companyId)
            ->whereNull('completed_at')
            ->where('is_archived', false)
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->visibleToCompany($companyId)
            ->with(['list:id,name'])
            ->orderByRaw('due_date ASC NULLS LAST')
            ->limit(5)
            ->get(['id', 'title', 'due_date', 'list_id'])
            ->map(fn (TaskCard $card) => [
                'id' => $card->id,
                'title' => $card->title,
                'due_date' => $card->due_date?->toDateString(),
                'list_title' => $card->list?->name,
            ]);

        $upcomingCalendar = null;
        if ($company) {
            $today = Carbon::today()->startOfDay();
            $weekEnd = $today->copy()->addDays(7)->endOfDay();
            $calendarRange = StrategicCalendarPeriod::forCompany($company);
            if ($calendarRange) {
                $weekEnd = $weekEnd->min($calendarRange['end']);
            }

            $masters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
                StrategicCalendarItem::query()->forCompany($company)->with(['company:id,name', 'attachments']),
                $today,
                $weekEnd,
            )->orderBy('occurs_on')->orderBy('id')->get();

            $upcomingCalendar = StrategicCalendarOccurrenceExpander::expandCollection(
                $masters,
                $today,
                $weekEnd,
                'client.strategic-calendar.attachment-download',
            )->take(7)->values();
        }

        $calendarKindLabels = collect(StrategicCalendarItemKind::cases())->mapWithKeys(
            fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()]
        );

        $dashboardCalendar = null;
        if ($company && $company->hasStrategicCalendarEnabled()) {
            $requestedYear = max(2000, min(2100, (int) $request->input('cal_year', now()->year)));
            $requestedMonth = max(1, min(12, (int) $request->input('cal_month', now()->month)));
            $view = StrategicCalendarPeriod::resolveClientView($company, $requestedYear, $requestedMonth);
            $calYear = $view['year'];
            $calMonth = $view['month'];

            $monthStart = Carbon::create($calYear, $calMonth, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

            $range = $view['range'];
            $queryStart = $range ? max($monthStart, $range['start']) : $monthStart;
            $queryEnd = $range ? min($monthEnd, $range['end']) : $monthEnd;

            $masters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
                StrategicCalendarItem::query()->forCompany($company)->with(['company:id,name', 'attachments']),
                $queryStart,
                $queryEnd,
            )->orderBy('occurs_on')->orderBy('id')->get();

            $items = StrategicCalendarOccurrenceExpander::expandCollection(
                $masters,
                $queryStart,
                $queryEnd,
                'client.strategic-calendar.attachment-download',
            );

            $dashboardCalendar = [
                'year' => $calYear,
                'month' => $calMonth,
                'items' => $items,
                'kindLabels' => $calendarKindLabels,
                'visiblePeriod' => $view['visiblePeriod'],
                'canNavigatePrev' => $view['canNavigatePrev'],
                'canNavigateNext' => $view['canNavigateNext'],
            ];
        }

        $actionPlanHref = $lastSurvey ? route('client.surveys.action-plan', $lastSurvey->id) : null;

        return Inertia::render('Client/Dashboard', [
            'activeSurveys' => $activeSurveys,
            'lastSurvey' => $lastSurvey,
            'overallRisk' => $overall,
            'lastCampaign' => [
                'completion_rate' => $completionRate,
                'section_results' => $sectionResults,
            ],
            'pendingComplaintsCount' => $pendingComplaintsCount,
            'openActionPlanCount' => $openActionPlanCount,
            'openActionPlanItems' => $openActionPlanItems,
            'pendingTasks' => $pendingTasks,
            'upcomingCalendar' => $upcomingCalendar,
            'calendarKindLabels' => $calendarKindLabels,
            'actionPlanHref' => $actionPlanHref,
            'complaintsPublicUrl' => $complaintsPublicUrl,
            'dashboardCalendar' => $dashboardCalendar,
        ]);
    }
}
