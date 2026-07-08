<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notices\MarkNoticeRead;
use App\Actions\Notices\PublishCompanyNotice;
use App\Enums\CompanyNoticeAudience;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Support\Notices\UnreadNoticeCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyNoticeController extends Controller
{
    private const RECENT_LIMIT = 8;

    public function index(Request $request): Response
    {
        $query = CompanyNotice::query()
            ->with(['company:id,name', 'creator:id,name'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        return Inertia::render('Admin/Notices/Index', [
            'notices' => $query->paginate(20)->withQueryString(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['company_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Notices/Create', [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->filter(fn (Company $company) => $company->hasStrategicCalendarEnabled())
                ->values(),
        ]);
    }

    public function store(Request $request, PublishCompanyNotice $publishCompanyNotice): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        abort_unless($company->hasStrategicCalendarEnabled(), 422);

        $publishCompanyNotice->handle(
            companyId: (int) $data['company_id'],
            title: $data['title'],
            body: $data['body'],
            actor: $request->user(),
        );

        return redirect()
            ->route('admin.notices.index')
            ->with('success', 'Aviso publicado para a empresa.');
    }

    public function recent(Request $request, UnreadNoticeCounter $unreadNoticeCounter): JsonResponse
    {
        $user = $request->user();

        $notices = $this->talentsQuery()
            ->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (CompanyNotice $notice) => $this->serializeNotice($notice));

        return response()->json([
            'notices' => $notices,
            'unread_count' => $unreadNoticeCounter->forUser($user),
        ]);
    }

    public function markRead(
        Request $request,
        CompanyNotice $notice,
        MarkNoticeRead $markNoticeRead,
        UnreadNoticeCounter $unreadNoticeCounter,
    ): RedirectResponse|JsonResponse {
        abort_unless($notice->audience === CompanyNoticeAudience::Talents, 404);

        $user = $request->user();
        $markNoticeRead->handle($notice, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadNoticeCounter->forUser($user),
            ]);
        }

        return back();
    }

    public function markAllRead(
        Request $request,
        MarkNoticeRead $markNoticeRead,
        UnreadNoticeCounter $unreadNoticeCounter,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $count = $markNoticeRead->markAllForContext($user, CompanyNoticeAudience::Talents, null);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'marked' => $count,
                'unread_count' => $unreadNoticeCounter->forUser($user),
            ]);
        }

        return back()->with('success', $count > 0
            ? 'Todos os avisos foram marcados como lidos.'
            : 'Não há avisos novos.');
    }

    private function talentsQuery()
    {
        return CompanyNotice::query()
            ->where('audience', CompanyNoticeAudience::Talents->value)
            ->whereNull('company_id')
            ->where('published_at', '<=', now());
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotice(CompanyNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'published_at' => $notice->published_at?->toIso8601String(),
            'event_kind' => $notice->event_kind?->value,
            'read' => $notice->reads->isNotEmpty(),
        ];
    }
}
