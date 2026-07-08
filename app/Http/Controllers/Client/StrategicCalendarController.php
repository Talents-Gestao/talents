<?php

namespace App\Http\Controllers\Client;

use App\Actions\Tasks\ToggleTaskCardCompletion;
use App\Enums\StrategicCalendarItemKind;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StrategicCalendarCompletion;
use App\Models\StrategicCalendarItem;
use App\Models\StrategicCalendarItemAttachment;
use App\Models\TaskCard;
use App\Support\StrategicCalendarClientEnricher;
use App\Support\StrategicCalendarOccurrenceExpander;
use App\Support\StrategicCalendarPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StrategicCalendarController extends Controller
{
    public function index(Request $request): InertiaResponse
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
        $queryStart = $range ? max($monthStart, $range['start']) : $monthStart;
        $queryEnd = $range ? min($monthEnd, $range['end']) : $monthEnd;

        $monthMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            StrategicCalendarItem::query()->forCompany($company)->with(['company:id,name', 'companies:id,name', 'attachments']),
            $queryStart,
            $queryEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $monthItems = StrategicCalendarOccurrenceExpander::expandCollection(
            $monthMasters,
            $queryStart,
            $queryEnd,
            'client.strategic-calendar.attachment-download',
        );
        $monthItems = StrategicCalendarClientEnricher::enrich($monthItems, $company, $queryStart, $queryEnd);

        $upcomingStart = now()->startOfDay();
        $upcomingEnd = $range
            ? $range['end']->copy()->endOfDay()
            : now()->copy()->addYears(2)->endOfDay();

        $upcomingMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            StrategicCalendarItem::query()->forCompany($company)->with(['company:id,name', 'companies:id,name', 'attachments']),
            $upcomingStart,
            $upcomingEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $upcomingExpanded = StrategicCalendarOccurrenceExpander::expandCollection(
            $upcomingMasters,
            $upcomingStart,
            $upcomingEnd,
            'client.strategic-calendar.attachment-download',
        );

        $upcoming = $upcomingExpanded
            ->filter(fn (array $row) => $row['occurs_on'] >= $upcomingStart->toDateString())
            ->pipe(fn (Collection $rows) => StrategicCalendarClientEnricher::enrich($rows, $company, $upcomingStart, $upcomingEnd))
            ->sortBy([['occurs_on', 'asc'], ['kind', 'asc']])
            ->take(12)
            ->values();

        $agendaStart = now()->toDateString();
        $agendaEndCarbon = now()->copy()->addDays(60)->endOfDay();
        if ($range) {
            $agendaEndCarbon = $agendaEndCarbon->min($range['end']);
        }

        $agendaMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            StrategicCalendarItem::query()->forCompany($company)->with(['company:id,name', 'companies:id,name', 'attachments']),
            Carbon::parse($agendaStart)->startOfDay(),
            $agendaEndCarbon,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $agendaItems = StrategicCalendarOccurrenceExpander::expandCollection(
            $agendaMasters,
            Carbon::parse($agendaStart)->startOfDay(),
            $agendaEndCarbon,
            'client.strategic-calendar.attachment-download',
        );
        $agendaItems = StrategicCalendarClientEnricher::enrich(
            $agendaItems,
            $company,
            Carbon::parse($agendaStart)->startOfDay(),
            $agendaEndCarbon,
        );

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
            )->merge(['task' => 'Tarefa']),
            'maxAttachmentMb' => $this->maxAttachmentMb(),
        ]);
    }

    public function toggleCompletion(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $user = $request->user();
        $company = $user->company;

        $data = $request->validate([
            'occurs_on' => ['required', 'date'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $visible = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereKey($item->id)
            ->exists();

        abort_unless($visible, 404);

        $occursOn = Carbon::parse($data['occurs_on'])->toDateString();
        $completed = array_key_exists('completed', $data)
            ? (bool) $data['completed']
            : ! StrategicCalendarCompletion::query()
                ->where('company_id', $company->id)
                ->where('strategic_calendar_item_id', $item->id)
                ->whereDate('occurs_on', $occursOn)
                ->exists();

        if ($completed) {
            StrategicCalendarCompletion::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'strategic_calendar_item_id' => $item->id,
                    'occurs_on' => $occursOn,
                ],
                [
                    'completed_at' => now(),
                    'completed_by_user_id' => $user->id,
                ],
            );
        } else {
            StrategicCalendarCompletion::query()
                ->where('company_id', $company->id)
                ->where('strategic_calendar_item_id', $item->id)
                ->whereDate('occurs_on', $occursOn)
                ->delete();
        }

        return back()->with('success', $completed ? 'Item marcado como concluído.' : 'Item reaberto.');
    }

    public function toggleTaskCompletion(
        Request $request,
        TaskCard $card,
        ToggleTaskCardCompletion $toggleCompletion,
    ): RedirectResponse {
        $user = $request->user();
        $company = $user->company;

        $data = $request->validate([
            'completed' => ['nullable', 'boolean'],
        ]);

        $card = TaskCard::query()
            ->visibleToCompany((int) $company->id)
            ->whereKey($card->id)
            ->firstOrFail();

        abort_unless($company->hasTasksEnabled(), 404);
        abort_unless($user->can('view', $card), 403);

        $completed = array_key_exists('completed', $data)
            ? (bool) $data['completed']
            : $card->completed_at === null;

        $toggleCompletion->handle($card, $completed, $user);

        return back()->with('success', $completed ? 'Tarefa marcada como concluída.' : 'Tarefa reaberta.');
    }

    public function attachmentDownload(Request $request, StrategicCalendarItemAttachment $attachment): StreamedResponse|Response
    {
        $company = $request->user()->company;
        $item = $attachment->item;
        $item->loadMissing(['companies', 'company']);

        $visible = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereKey($item->id)
            ->exists();

        abort_unless($visible, 404);

        return $attachment->toHttpResponse();
    }

    private function maxAttachmentMb(): int
    {
        $maxKb = (int) config('strategic_calendar.max_attachment_kb', 524288);

        return (int) max(1, (int) floor($maxKb / 1024));
    }

}
