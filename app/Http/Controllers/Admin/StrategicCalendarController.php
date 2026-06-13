<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use App\Models\StrategicCalendarItemAttachment;
use App\Support\StrategicCalendarOccurrenceExpander;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StrategicCalendarController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));
        $month = max(1, min(12, (int) $request->input('month', now()->month)));

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        if ($request->filled('kind')) {
            $request->validate([
                'kind' => ['required', 'string', Rule::enum(StrategicCalendarItemKind::class)],
            ]);
        }

        $listQuery = StrategicCalendarItem::query()
            ->with(['company:id,name', 'attachments'])
            ->withCount('attachments')
            ->orderByDesc('occurs_on')
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $cid = (int) $request->input('company_id');
            $listQuery->where(function ($q) use ($cid) {
                $q->whereNull('company_id')->orWhere('company_id', $cid);
            });
        }

        if ($request->filled('kind')) {
            $listQuery->where('kind', $request->input('kind'));
        }

        $items = $listQuery->paginate(20)->withQueryString();

        $monthMasterQuery = $this->filteredMasterQuery($request);
        $monthMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            $monthMasterQuery,
            $monthStart,
            $monthEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $monthItems = StrategicCalendarOccurrenceExpander::expandCollection(
            $monthMasters,
            $monthStart,
            $monthEnd,
        );

        $agendaEnd = now()->copy()->addDays(60)->endOfDay();
        $agendaStart = now()->copy()->startOfDay();
        $agendaMasterQuery = $this->filteredMasterQuery($request);
        $agendaMasters = StrategicCalendarOccurrenceExpander::baseQueryForRange(
            $agendaMasterQuery,
            $agendaStart,
            $agendaEnd,
        )->orderBy('occurs_on')->orderBy('id')->get();

        $agendaItems = StrategicCalendarOccurrenceExpander::expandCollection(
            $agendaMasters,
            $agendaStart,
            $agendaEnd,
        );

        return Inertia::render('Admin/StrategicCalendar/Index', [
            'items' => $items,
            'monthItems' => $monthItems,
            'agendaItems' => $agendaItems,
            'calendarYear' => $year,
            'calendarMonth' => $month,
            'filters' => $request->only(['company_id', 'kind']),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'kindLabels' => collect(StrategicCalendarItemKind::cases())->mapWithKeys(
                fn (StrategicCalendarItemKind $k) => [$k->value => $k->label()]
            ),
            'recurrenceLabels' => collect(StrategicCalendarRecurrence::cases())->mapWithKeys(
                fn (StrategicCalendarRecurrence $r) => [$r->value => $r->label()]
            ),
            'kinds' => collect(StrategicCalendarItemKind::cases())->map(fn (StrategicCalendarItemKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ])->values()->all(),
            'recurrences' => $this->recurrenceOptions(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/StrategicCalendar/Create', [
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'kinds' => collect(StrategicCalendarItemKind::cases())->map(fn (StrategicCalendarItemKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ]),
            'recurrences' => $this->recurrenceOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        StrategicCalendarItem::query()->create($data);

        return back()->with('success', 'Item do calendário criado.');
    }

    public function edit(StrategicCalendarItem $item): InertiaResponse
    {
        $item->load('attachments');

        return Inertia::render('Admin/StrategicCalendar/Edit', [
            'item' => [
                ...$item->toArray(),
                'occurs_on' => $item->occurs_on?->toDateString(),
                'recurrence_ends_on' => $item->recurrence_ends_on?->toDateString(),
                'recurrence' => $item->recurrence?->value,
                'attachments' => $item->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->downloadName(),
                    'url' => route('admin.strategic-calendar.attachment-download', $a->id),
                ])->values()->all(),
            ],
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'kinds' => collect(StrategicCalendarItemKind::cases())->map(fn (StrategicCalendarItemKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ]),
            'recurrences' => $this->recurrenceOptions(),
        ]);
    }

    public function updateDate(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $data = $request->validate([
            'occurs_on' => ['required', 'date'],
        ]);

        $item->update(['occurs_on' => $data['occurs_on']]);

        return back()->with('success', 'Data do item atualizada.');
    }

    public function update(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $data = $this->validated($request);

        $item->update($data);

        return back()->with('success', 'Item atualizado.');
    }

    public function destroy(StrategicCalendarItem $item): RedirectResponse
    {
        $item->deleteAllAttachments();
        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function attachmentsStore(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $maxKb = (int) config('strategic_calendar.max_attachment_kb', 10240);

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.max(1, $maxKb)],
        ]);

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('strategic-calendar/'.$item->id, 'public');

            $item->attachments()->create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by_user_id' => $request->user()?->id,
            ]);
        }

        return back()->with('success', 'Anexo(s) enviado(s).');
    }

    public function attachmentDestroy(StrategicCalendarItemAttachment $attachment): RedirectResponse
    {
        $attachment->deleteStoredFile();
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }

    public function attachmentDownload(StrategicCalendarItemAttachment $attachment): StreamedResponse|Response
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->downloadName(),
        );
    }

    private function filteredMasterQuery(Request $request)
    {
        $query = StrategicCalendarItem::query()->with(['company:id,name', 'attachments']);

        if ($request->filled('company_id')) {
            $cid = (int) $request->input('company_id');
            $query->where(function ($q) use ($cid) {
                $q->whereNull('company_id')->orWhere('company_id', $cid);
            });
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }

        return $query;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function recurrenceOptions(): array
    {
        return collect(StrategicCalendarRecurrence::cases())
            ->map(fn (StrategicCalendarRecurrence $r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'recurrence' => $request->input('recurrence') ?: null,
            'recurrence_ends_on' => $request->input('recurrence_ends_on') ?: null,
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'kind' => ['required', 'string', Rule::enum(StrategicCalendarItemKind::class)],
            'occurs_on' => ['required', 'date'],
            'recurrence' => ['nullable', Rule::enum(StrategicCalendarRecurrence::class)],
            'recurrence_ends_on' => ['nullable', 'date', 'after_or_equal:occurs_on'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        if (empty($data['recurrence'])) {
            $data['recurrence'] = null;
            $data['recurrence_ends_on'] = null;
        }

        $data['company_id'] = $data['company_id'] ?? null;

        return $data;
    }
}
