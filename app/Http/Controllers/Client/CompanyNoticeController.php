<?php

namespace App\Http\Controllers\Client;

use App\Actions\Notices\DeleteCompanyNotice;
use App\Actions\Notices\MarkNoticeRead;
use App\Http\Controllers\Controller;
use App\Models\CompanyNotice;
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
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $notices = CompanyNotice::query()
            ->where('company_id', $companyId)
            ->where('published_at', '<=', now())
            ->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CompanyNotice $notice) => $this->serializeNotice($notice));

        return Inertia::render('Client/Notices/Index', [
            'notices' => $notices,
        ]);
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
        abort_unless((int) $notice->company_id === (int) $user->company_id, 404);

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
        $count = $markNoticeRead->markAllForUser($user, (int) $user->company_id);

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
        abort_unless((int) $notice->company_id === (int) $user->company_id, 404);

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
        abort_unless((int) $notice->company_id === (int) $user->company_id, 404);

        $markNoticeRead->handle($notice, $user);

        $url = $destination->url($notice, admin: false);

        return redirect()->to($url ?? url()->previous(route('client.notices.index')));
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
            'url' => app(NoticeDestinationUrl::class)->url($notice, admin: false),
        ];
    }
}
