<?php

namespace App\Http\Controllers\Client;

use App\Actions\Notices\MarkNoticeRead;
use App\Http\Controllers\Controller;
use App\Models\CompanyNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyNoticeController extends Controller
{
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
            ->through(fn (CompanyNotice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'published_at' => $notice->published_at?->toIso8601String(),
                'event_kind' => $notice->event_kind?->value,
                'read' => $notice->reads->isNotEmpty(),
            ]);

        return Inertia::render('Client/Notices/Index', [
            'notices' => $notices,
        ]);
    }

    public function markRead(Request $request, CompanyNotice $notice, MarkNoticeRead $markNoticeRead): RedirectResponse
    {
        $user = $request->user();
        abort_unless((int) $notice->company_id === (int) $user->company_id, 404);

        $markNoticeRead->handle($notice, $user);

        return back();
    }

    public function markAllRead(Request $request, MarkNoticeRead $markNoticeRead): RedirectResponse
    {
        $user = $request->user();
        $count = $markNoticeRead->markAllForUser($user, (int) $user->company_id);

        return back()->with('success', $count > 0
            ? 'Todos os avisos foram marcados como lidos.'
            : 'Não há avisos novos.');
    }
}
