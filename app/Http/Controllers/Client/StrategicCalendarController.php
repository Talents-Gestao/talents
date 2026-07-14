<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Tasks\ToggleTaskCardCompletion;
use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use App\Enums\StrategicCalendarSource;
use App\Http\Controllers\Controller;
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
use Illuminate\Validation\Rule;
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
        $agenda = $this->normalizeAgendaFilter($request->input('agenda'));

        $view = StrategicCalendarPeriod::resolveClientView($company, $requestedYear, $requestedMonth);
        $year = $view['year'];
        $month = $view['month'];

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $range = $view['range'];
        $queryStart = $range ? max($monthStart, $range['start']) : $monthStart;
        $queryEnd = $range ? min($monthEnd, $range['end']) : $monthEnd;

        $baseQuery = fn () => StrategicCalendarItem::query()
            ->forCompany($company)
            ->ofAgenda($agenda)
            ->with(['company:id,name', 'companies:id,name', 'attachments']);

        $monthMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            $baseQuery(),
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
        $monthItems = $this->markManageable($monthItems);

        $upcomingStart = now()->startOfDay();
        $upcomingEnd = $range
            ? $range['end']->copy()->endOfDay()
            : now()->copy()->addYears(2)->endOfDay();

        $upcomingMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            $baseQuery(),
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
            ->pipe(fn (Collection $rows) => $this->markManageable($rows))
            ->sortBy([['occurs_on', 'asc'], ['kind', 'asc']])
            ->take(12)
            ->values();

        $agendaStart = now()->toDateString();
        $agendaEndCarbon = now()->copy()->addDays(60)->endOfDay();
        if ($range) {
            $agendaEndCarbon = $agendaEndCarbon->min($range['end']);
        }

        $agendaMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            $baseQuery(),
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
        $agendaItems = $this->markManageable($agendaItems);

        return Inertia::render('Client/StrategicCalendar/Index', [
            'monthItems' => $monthItems,
            'upcoming' => $upcoming,
            'agendaItems' => $agendaItems,
            'calendarYear' => $year,
            'calendarMonth' => $month,
            'agendaFilter' => $agenda ?? 'all',
            'visiblePeriod' => $view['visiblePeriod'],
            'canNavigatePrev' => $view['canNavigatePrev'],
            'canNavigateNext' => $view['canNavigateNext'],
            'kindLabels' => collect(StrategicCalendarItemKind::cases())->mapWithKeys(
                fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()]
            )->merge(['task' => 'Tarefa']),
            'kinds' => collect([StrategicCalendarItemKind::Event, StrategicCalendarItemKind::Ritual])
                ->map(fn (StrategicCalendarItemKind $k) => [
                    'value' => $k->value,
                    'label' => $k->label(),
                ])
                ->values()
                ->all(),
            'recurrences' => collect(StrategicCalendarRecurrence::cases())
                ->map(fn (StrategicCalendarRecurrence $r) => [
                    'value' => $r->value,
                    'label' => $r->label(),
                ])
                ->values()
                ->all(),
            'recurrenceLabels' => collect(StrategicCalendarRecurrence::cases())->mapWithKeys(
                fn (StrategicCalendarRecurrence $r) => [$r->value => $r->label()]
            ),
            'maxAttachmentMb' => $this->maxAttachmentMb(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->company;
        $data = $this->validatedClientPayload($request);

        StrategicCalendarItem::query()->create([
            ...$data,
            'company_id' => $company->id,
            'source' => StrategicCalendarSource::Company,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Evento da agenda interna criado.');
    }

    public function update(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $this->assertCompanyOwnedItem($request, $item);
        $data = $this->validatedClientPayload($request);

        $item->update([
            ...$data,
            'company_id' => $request->user()->company->id,
            'source' => StrategicCalendarSource::Company,
        ]);

        return back()->with('success', 'Evento da agenda interna atualizado.');
    }

    public function destroy(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $this->assertCompanyOwnedItem($request, $item);

        $item->deleteAllAttachments();
        $item->delete();

        return back()->with('success', 'Evento da agenda interna removido.');
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

    /**
     * @return array{
     *   title: string,
     *   description: ?string,
     *   kind: StrategicCalendarItemKind,
     *   occurs_on: string,
     *   ends_on: ?string,
     *   recurrence: ?StrategicCalendarRecurrence,
     *   recurrence_ends_on: ?string
     * }
     */
    private function validatedClientPayload(Request $request): array
    {
        $request->merge([
            'recurrence' => $request->input('recurrence') ?: null,
            'recurrence_ends_on' => $request->input('recurrence_ends_on') ?: null,
            'ends_on' => $request->input('ends_on') ?: null,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'kind' => ['required', Rule::in([
                StrategicCalendarItemKind::Event->value,
                StrategicCalendarItemKind::Ritual->value,
            ])],
            'occurs_on' => ['required', 'date'],
            'ends_on' => [
                'nullable',
                'date',
                'after_or_equal:occurs_on',
                Rule::prohibitedIf(fn () => filled($request->input('recurrence'))),
            ],
            'recurrence' => [
                'nullable',
                Rule::enum(StrategicCalendarRecurrence::class),
                Rule::prohibitedIf(fn () => filled($request->input('ends_on'))),
            ],
            'recurrence_ends_on' => ['nullable', 'date', 'after_or_equal:occurs_on'],
        ]);

        if (empty($data['recurrence'])) {
            $data['recurrence'] = null;
            $data['recurrence_ends_on'] = null;
        }

        if (empty($data['ends_on']) || $data['ends_on'] === $data['occurs_on']) {
            $data['ends_on'] = null;
        }

        $data['kind'] = $data['kind'] instanceof StrategicCalendarItemKind
            ? $data['kind']->value
            : (string) $data['kind'];

        if (! empty($data['recurrence']) && ! ($data['recurrence'] instanceof StrategicCalendarRecurrence)) {
            $data['recurrence'] = (string) $data['recurrence'];
        }

        return $data;
    }

    private function assertCompanyOwnedItem(Request $request, StrategicCalendarItem $item): void
    {
        $company = $request->user()->company;

        $visible = StrategicCalendarItem::query()
            ->forCompany($company)
            ->whereKey($item->id)
            ->exists();

        abort_unless($visible, 404);
        abort_unless($item->isCompanyAgenda() && (int) $item->company_id === (int) $company->id, 403);
    }

    private function normalizeAgendaFilter(mixed $agenda): ?string
    {
        $value = is_string($agenda) ? $agenda : 'all';

        return match ($value) {
            StrategicCalendarSource::Talents->value,
            StrategicCalendarSource::Company->value => $value,
            default => null,
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function markManageable(Collection $rows): Collection
    {
        return $rows->map(function (array $row) {
            $row['can_manage'] = ($row['agenda'] ?? null) === StrategicCalendarSource::Company->value
                && ($row['source_type'] ?? 'strategic_item') !== 'task';

            return $row;
        });
    }

    private function maxAttachmentMb(): int
    {
        $maxKb = (int) config('strategic_calendar.max_attachment_kb', 524288);

        return (int) max(1, (int) floor($maxKb / 1024));
    }
}
