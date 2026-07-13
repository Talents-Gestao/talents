<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notices\PublishStrategicCalendarChangeNotice;
use App\Enums\CompanyNoticeEventKind;
use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StrategicCalendarItem;
use App\Models\StrategicCalendarItemAttachment;
use App\Support\StrategicCalendarAudience;
use App\Support\StrategicCalendarOccurrenceExpander;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            ->with(['company:id,name', 'companies:id,name', 'attachments'])
            ->withCount('attachments')
            ->orderByDesc('occurs_on')
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $cid = (int) $request->input('company_id');
            $listQuery->where(function ($q) use ($cid) {
                $q->where(function ($global) {
                    $global->whereNull('company_id')->whereDoesntHave('companies');
                })
                    ->orWhere('company_id', $cid)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $cid));
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
            'maxAttachmentMb' => $this->maxAttachmentMb(),
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

    public function store(Request $request, PublishStrategicCalendarChangeNotice $publishNotice): RedirectResponse
    {
        $data = $this->validated($request);
        $companyIds = $data['company_ids'] ?? [];
        unset($data['company_ids']);

        $item = StrategicCalendarItem::query()->create($data);
        StrategicCalendarAudience::syncCompanies($item, $companyIds);
        $item->load(['companies', 'company']);
        $publishNotice->handle($item, CompanyNoticeEventKind::Created, $request->user());

        return back()->with('success', 'Item do calendário criado.');
    }

    public function edit(StrategicCalendarItem $item): InertiaResponse
    {
        $item->load(['attachments', 'companies:id,name', 'company:id,name']);

        return Inertia::render('Admin/StrategicCalendar/Edit', [
            'item' => [
                ...$item->toArray(),
                'occurs_on' => $item->occurs_on?->toDateString(),
                'ends_on' => $item->ends_on?->toDateString(),
                'recurrence_ends_on' => $item->recurrence_ends_on?->toDateString(),
                'recurrence' => $item->recurrence?->value,
                'company_ids' => StrategicCalendarAudience::targetCompanyIds($item),
                'audience_label' => StrategicCalendarAudience::label($item),
                'attachments' => $item->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->downloadName(),
                    'url' => route('admin.strategic-calendar.attachment-download', $a->id),
                    'mime' => $a->mime,
                    'size' => $a->size,
                ])->values()->all(),
            ],
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'kinds' => collect(StrategicCalendarItemKind::cases())->map(fn (StrategicCalendarItemKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ]),
            'recurrences' => $this->recurrenceOptions(),
            'maxAttachmentMb' => $this->maxAttachmentMb(),
        ]);
    }

    public function updateDate(Request $request, StrategicCalendarItem $item, PublishStrategicCalendarChangeNotice $publishNotice): RedirectResponse
    {
        $data = $request->validate([
            'occurs_on' => ['required', 'date'],
        ]);

        $previous = $item->occurs_on?->toDateString();
        $previousEndsOn = $item->ends_on?->toDateString();
        $durationDays = null;
        if ($previous && $previousEndsOn) {
            $durationDays = Carbon::parse($previous)->diffInDays(Carbon::parse($previousEndsOn));
        }

        $item->update(['occurs_on' => $data['occurs_on']]);

        if ($durationDays !== null) {
            $item->update([
                'ends_on' => Carbon::parse($data['occurs_on'])->addDays($durationDays)->toDateString(),
            ]);
        }

        $item->refresh();

        $publishNotice->handle(
            $item,
            CompanyNoticeEventKind::DateChanged,
            $request->user(),
            $previous,
        );

        return back()->with('success', 'Data do item atualizada.');
    }

    public function update(Request $request, StrategicCalendarItem $item, PublishStrategicCalendarChangeNotice $publishNotice): RedirectResponse
    {
        $data = $this->validated($request);
        $companyIds = $data['company_ids'] ?? [];
        unset($data['company_ids']);

        $item->update($data);
        StrategicCalendarAudience::syncCompanies($item, $companyIds);
        $item->load(['companies', 'company']);
        $item->refresh();

        $publishNotice->handle($item, CompanyNoticeEventKind::Updated, $request->user());

        return back()->with('success', 'Item atualizado.');
    }

    public function destroy(StrategicCalendarItem $item, Request $request, PublishStrategicCalendarChangeNotice $publishNotice): RedirectResponse
    {
        $publishNotice->handle($item, CompanyNoticeEventKind::Deleted, $request->user());

        $item->deleteAllAttachments();
        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    public function attachmentsStore(Request $request, StrategicCalendarItem $item): RedirectResponse
    {
        $maxKb = (int) config('strategic_calendar.max_attachment_kb', 524288);

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
        return $attachment->toHttpResponse();
    }

    private function maxAttachmentMb(): int
    {
        $maxKb = (int) config('strategic_calendar.max_attachment_kb', 524288);

        return (int) max(1, (int) floor($maxKb / 1024));
    }

    private function filteredMasterQuery(Request $request)
    {
        $query = StrategicCalendarItem::query()->with(['company:id,name', 'companies:id,name', 'attachments']);

        if ($request->filled('company_id')) {
            $cid = (int) $request->input('company_id');
            $query->where(function ($q) use ($cid) {
                $q->where(function ($global) {
                    $global->whereNull('company_id')->whereDoesntHave('companies');
                })
                    ->orWhere('company_id', $cid)
                    ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $cid));
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
            'ends_on' => $request->input('ends_on') ?: null,
            'company_ids' => $request->input('company_ids', []),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'kind' => ['required', 'string', Rule::enum(StrategicCalendarItemKind::class)],
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
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
        ]);

        if (empty($data['recurrence'])) {
            $data['recurrence'] = null;
            $data['recurrence_ends_on'] = null;
        }

        if (empty($data['ends_on']) || $data['ends_on'] === $data['occurs_on']) {
            $data['ends_on'] = null;
        }

        $data['company_ids'] = collect($data['company_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['company_id']);

        return $data;
    }
}
