<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notices\DeleteCompanyNotice;
use App\Actions\Notices\MarkNoticeRead;
use App\Actions\Notices\PublishCompanyNotice;
use App\Enums\CompanyNoticeEventKind;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyNotice;
use App\Support\Complaints\ComplaintCompanyContext;
use App\Support\Feedback\FeedbackCompanyContext;
use App\Support\Notices\NoticeDestinationUrl;
use App\Support\Notices\UnreadNoticeCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyNoticeController extends Controller
{
    private const BELL_PAGE_SIZE = 50;

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
        $page = max(1, $request->integer('page', 1));
        $result = $unreadNoticeCounter->paginateForBell($user, $page, self::BELL_PAGE_SIZE);
        abort_unless($result !== null, 403);

        return response()->json([
            'notices' => $result['notices']
                ->map(fn (CompanyNotice $notice) => $this->serializeNotice($notice))
                ->values(),
            'unread_count' => $unreadNoticeCounter->forUser($user),
            'page' => $result['page'],
            'has_more' => $result['has_more'],
        ]);
    }

    public function markRead(
        Request $request,
        CompanyNotice $notice,
        MarkNoticeRead $markNoticeRead,
        UnreadNoticeCounter $unreadNoticeCounter,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $visible = $unreadNoticeCounter->visibleNoticesQuery($user);
        abort_unless(
            $visible !== null && (clone $visible)->whereKey($notice->id)->exists(),
            404,
        );

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
        $count = $markNoticeRead->markAllVisibleForUser($user, $unreadNoticeCounter);

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

    public function destroy(
        Request $request,
        CompanyNotice $notice,
        DeleteCompanyNotice $deleteCompanyNotice,
        UnreadNoticeCounter $unreadNoticeCounter,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $visible = $unreadNoticeCounter->visibleNoticesQuery($user);
        abort_unless(
            $visible !== null && (clone $visible)->whereKey($notice->id)->exists(),
            404,
        );

        $deleteCompanyNotice->handle($notice, $user);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'unread_count' => $unreadNoticeCounter->forUser($user),
            ]);
        }

        return back()->with('success', 'Aviso excluído.');
    }

    public function destroyAll(
        Request $request,
        DeleteCompanyNotice $deleteCompanyNotice,
        UnreadNoticeCounter $unreadNoticeCounter,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        $count = $deleteCompanyNotice->deleteAllVisibleForUser($user);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'deleted' => $count,
                'unread_count' => $unreadNoticeCounter->forUser($user),
            ]);
        }

        return back()->with('success', $count > 0
            ? 'Todos os avisos foram excluídos.'
            : 'Não há avisos para excluir.');
    }

    public function open(
        Request $request,
        CompanyNotice $notice,
        MarkNoticeRead $markNoticeRead,
        UnreadNoticeCounter $unreadNoticeCounter,
        NoticeDestinationUrl $destination,
    ): RedirectResponse {
        $user = $request->user();
        $visible = $unreadNoticeCounter->visibleNoticesQuery($user);
        abort_unless(
            $visible !== null && (clone $visible)->whereKey($notice->id)->exists(),
            404,
        );

        $markNoticeRead->handle($notice, $user);
        $this->prepareAdminModuleContext($notice, $request);

        $url = $destination->url($notice, admin: true);

        return redirect()->to($url ?? url()->previous(route('admin.dashboard')));
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
            'audience' => $notice->audience?->value,
            'company_id' => $notice->company_id,
            'company_name' => $notice->company?->name,
            'read' => $notice->reads->isNotEmpty(),
            'url' => app(NoticeDestinationUrl::class)->url($notice, admin: true),
        ];
    }

    private function prepareAdminModuleContext(CompanyNotice $notice, Request $request): void
    {
        $companyId = $notice->company_id;
        if (! $companyId) {
            return;
        }

        $kind = $notice->event_kind;
        if (in_array($kind, [
            CompanyNoticeEventKind::FeedbackAwaitingSignature,
            CompanyNoticeEventKind::FeedbackCompleted,
        ], true)) {
            $request->session()->put(FeedbackCompanyContext::SESSION_KEY, (int) $companyId);
        }

        if (in_array($kind, [
            CompanyNoticeEventKind::ComplaintCreated,
            CompanyNoticeEventKind::ComplaintUpdated,
        ], true)) {
            $request->session()->put(ComplaintCompanyContext::SESSION_KEY, (int) $companyId);
        }
    }
}
