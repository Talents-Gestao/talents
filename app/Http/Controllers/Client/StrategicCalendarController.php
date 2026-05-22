<?php

namespace App\Http\Controllers\Client;

use App\Enums\StrategicCalendarItemKind;
use App\Http\Controllers\Controller;
use App\Models\StrategicCalendarItem;
use App\Support\StrategicCalendarPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StrategicCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->company;

        $requestedYear = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $requestedMonth = max(1, min(12, (int) $request->input('month', now()->month)));

        $view = StrategicCalendarPeriod::resolveClientView($company, $requestedYear, $requestedMonth);
        $year = $view['year'];
        $month = $view['month'];

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $range = $view['range'];
        $periodStart = $range ? $range['start']->toDateString() : null;
        $periodEnd = $range ? $range['end']->toDateString() : null;

        $monthQueryStart = $range
            ? max($monthStart->toDateString(), $periodStart)
            : $monthStart->toDateString();
        $monthQueryEnd = $range
            ? min($monthEnd->toDateString(), $periodEnd)
            : $monthEnd->toDateString();

        $monthItems = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereBetween('occurs_on', [$monthQueryStart, $monthQueryEnd])
            ->orderBy('occurs_on')
            ->orderBy('id')
            ->get();

        $upcomingQuery = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereDate('occurs_on', '>=', now()->toDateString())
            ->orderBy('occurs_on')
            ->orderBy('id');

        if ($range) {
            $upcomingQuery->whereDate('occurs_on', '<=', $periodEnd);
        }

        $upcoming = $upcomingQuery->limit(12)->get();

        $agendaStart = now()->toDateString();
        $agendaEndCarbon = now()->copy()->addDays(60)->endOfDay();
        if ($range) {
            $agendaEndCarbon = $agendaEndCarbon->min($range['end']);
        }

        $agendaItems = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereDate('occurs_on', '>=', $agendaStart)
            ->whereDate('occurs_on', '<=', $agendaEndCarbon->toDateString())
            ->orderBy('occurs_on')
            ->orderBy('id')
            ->get();

        return Inertia::render('Client/StrategicCalendar/Index', [
            'monthItems' => $monthItems,
            'upcoming' => $upcoming,
            'agendaItems' => $agendaItems,
            'calendarYear' => $year,
            'calendarMonth' => $month,
            'visiblePeriod' => $view['visiblePeriod'],
            'canNavigatePrev' => $view['canNavigatePrev'],
            'canNavigateNext' => $view['canNavigateNext'],
            'kindLabels' => collect(StrategicCalendarItemKind::cases())->mapWithKeys(
                fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()]
            ),
        ]);
    }
}
