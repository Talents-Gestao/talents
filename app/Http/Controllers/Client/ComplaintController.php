<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Actions\Notices\PublishComplaintNotice;
use App\Http\Controllers\Concerns\ResolvesComplaintRoutes;
use App\Http\Controllers\Controller;
use App\Mail\ComplaintReporterNotificationMail;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Services\ComplaintAuditService;
use App\Support\Complaints\ComplaintCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    use ResolvesComplaintRoutes;

    private function companyId(Request $request): int
    {
        return app(ComplaintCompanyContext::class)->resolve($request)->id;
    }

    public function index(Request $request): Response
    {
        $context = app(ComplaintCompanyContext::class);

        if ($context->needsCompanySelection($request)) {
            return Inertia::render('Client/Complaints/Index', [
                'complaints' => ['data' => [], 'links' => []],
                'companyPicker' => $context->availableCompanies(),
                'activeCompany' => null,
                'isAdminContext' => true,
            ]);
        }

        $companyId = $this->companyId($request);

        $complaints = Complaint::query()
            ->where('company_id', $companyId)
            ->with('department')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Complaint $c) => [
                'id' => $c->id,
                'protocol' => $c->protocol,
                'category' => $c->category,
                'status' => $c->status,
                'is_anonymous' => $c->is_anonymous,
                'department_name' => $c->department?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Client/Complaints/Index', [
            'complaints' => $complaints,
            'companyPicker' => $context->isAdminContext($request) ? $context->availableCompanies() : null,
            'activeCompany' => $context->resolve($request)->only(['id', 'name']),
            'isAdminContext' => $context->isAdminContext($request),
        ]);
    }

    public function show(Request $request, Complaint $complaint): Response
    {
        abort_unless($complaint->company_id === $this->companyId($request), 404);

        $complaint->load([
            'department',
            'messages' => fn ($q) => $q->orderBy('id'),
        ]);

        ComplaintAuditService::log($complaint, 'viewed_by_company', $request, $request->user());

        return Inertia::render('Client/Complaints/Show', [
            'complaint' => [
                'id' => $complaint->id,
                'protocol' => $complaint->protocol,
                'category' => $complaint->category,
                'status' => $complaint->status,
                'department_name' => $complaint->department?->name,
                'is_anonymous' => $complaint->is_anonymous,
                'reporter_name' => $complaint->is_anonymous ? null : $complaint->safeDecrypt('reporter_name', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                'reporter_email' => $complaint->is_anonymous ? null : $complaint->safeDecrypt('reporter_email', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                'description' => $complaint->safeDecrypt('description', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                'created_at' => $complaint->created_at?->toIso8601String(),
                'resolved_at' => $complaint->resolved_at?->toIso8601String(),
                'messages' => $complaint->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'author_type' => $m->author_type,
                    'content' => $m->safeDecrypt('content', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                    'created_at' => $m->created_at?->toIso8601String(),
                    'user' => $m->user ? ['name' => $m->user->name] : null,
                ]),
                'audit_logs' => $complaint->auditLogs()->with('user')->orderByDesc('id')->limit(50)->get()->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'meta' => $log->meta,
                    'ip_address' => $log->ip_address,
                    'user' => $log->user ? ['name' => $log->user->name] : null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            ],
            'isAdminContext' => app(ComplaintCompanyContext::class)->isAdminContext($request),
        ]);
    }

    public function updateStatus(Request $request, Complaint $complaint, PublishComplaintNotice $notices): RedirectResponse
    {
        abort_unless($complaint->company_id === $this->companyId($request), 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,under_review,resolved,archived'],
        ]);

        $previous = $complaint->status;
        $resolvedAt = $complaint->resolved_at;
        if ($data['status'] === 'resolved') {
            $resolvedAt = $resolvedAt ?? now();
        } elseif ($data['status'] !== 'archived') {
            $resolvedAt = null;
        }
        $complaint->update([
            'status' => $data['status'],
            'resolved_at' => $resolvedAt,
        ]);

        ComplaintAuditService::log($complaint, 'status_changed', $request, $request->user(), [
            'from' => $previous,
            'to' => $data['status'],
        ]);

        if ($previous !== $data['status']) {
            $this->notifyReporterIfIdentified($complaint, 'status', ['status' => $data['status']]);
            $notices->statusUpdated($complaint, $previous, $data['status'], $request->user());
        }

        return back()->with('success', 'Status atualizado.');
    }

    public function storeMessage(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->company_id === $this->companyId($request), 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'author_type' => ComplaintMessage::AUTHOR_COMPANY,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        ComplaintAuditService::log($complaint, 'message_added_by_company', $request, $request->user());

        $this->notifyReporterIfIdentified($complaint, 'message');

        return back()->with('success', 'Resposta registrada.');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function notifyReporterIfIdentified(Complaint $complaint, string $eventType, array $meta = []): void
    {
        if ($complaint->is_anonymous) {
            return;
        }

        $email = $complaint->reporter_email;
        if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $complaint->loadMissing('company');

        Mail::to($email)->send(new ComplaintReporterNotificationMail($complaint, $eventType, $meta));
    }
}
