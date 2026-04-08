<?php

namespace App\Http\Controllers\Client;

use App\Enums\StrategicCalendarItemKind;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use App\Models\Survey;
use App\Models\SurveyResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $companyId = $request->user()->company_id;

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
        if ($lastSurvey) {
            $overall = SurveyResult::query()
                ->where('survey_id', $lastSurvey->id)
                ->whereNull('survey_template_section_id')
                ->whereNull('department_id')
                ->first();
        }

        $company = Company::query()->find($companyId);
        $complaintsPublicUrl = $company?->complaints_public_token
            ? url('/denuncia/'.$company->complaints_public_token)
            : null;

        $dashboardCalendar = null;
        if ($company && $company->hasStrategicCalendarEnabled()) {
            $calYear = max(2000, min(2100, (int) $request->input('cal_year', now()->year)));
            $calMonth = max(1, min(12, (int) $request->input('cal_month', now()->month)));
            $monthStart = Carbon::create($calYear, $calMonth, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

            $items = StrategicCalendarItem::query()
                ->forCompany($company)
                ->whereBetween('occurs_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->orderBy('occurs_on')
                ->orderBy('id')
                ->get();

            $dashboardCalendar = [
                'year' => $calYear,
                'month' => $calMonth,
                'items' => $items,
                'kindLabels' => collect(StrategicCalendarItemKind::cases())->mapWithKeys(
                    fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()]
                ),
            ];
        }

        return Inertia::render('Client/Dashboard', [
            'activeSurveys' => $activeSurveys,
            'lastSurvey' => $lastSurvey,
            'overallRisk' => $overall,
            'complaintsPublicUrl' => $complaintsPublicUrl,
            'dashboardCalendar' => $dashboardCalendar,
        ]);
    }
}
