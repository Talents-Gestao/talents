<?php

namespace App\Http\Controllers\Client;

use App\Actions\Notices\MarkNoticeRead;
use App\Http\Controllers\Controller;
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
        $companyId = (int) $user->company_id;

        $notices = CompanyNotice::query()
            ->where('company_id', $companyId)
            ->where('published_at', '<=', now())
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
