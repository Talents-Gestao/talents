<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WorkspaceType;
use App\Exceptions\PsychosocialDocumentException;
use App\Http\Controllers\Controller;
use App\Models\ApproverOrganization;
use App\Models\User;
use App\Services\Psychosocial\DocumentShareService;
use App\Services\Psychosocial\ExternalReviewerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Revisores externos (PASSEG): a Talents cadastra nome, e-mail e organização aprovadora e envia o convite. O cadastro não
 * libera acesso enquanto a revisão externa estiver desligada (MFA).
 */
class ExternalReviewerAdminController extends Controller
{
    public function __construct(private readonly ExternalReviewerService $reviewers) {}

    public function index(): Response
    {
        $users = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspace_type', WorkspaceType::ExternalReview))
            ->with(['workspaces' => fn ($q) => $q->where('workspace_type', WorkspaceType::ExternalReview)->with('approverOrganization:id,name')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/ExternalReviewers/Index', [
            'reviewers' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization' => $user->workspaces->first()?->approverOrganization?->name,
                'status' => ! $user->is_active ? 'inactive' : ($user->hasCompletedRegistration() ? 'active' : 'pending'),
                'status_label' => ! $user->is_active ? 'Inativo' : ($user->hasCompletedRegistration() ? 'Senha definida' : 'Convite pendente'),
                'mfa_active' => app(\App\Services\Mfa\MfaService::class)->isActive($user),
            ])->values(),
            'organizations' => ApproverOrganization::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'externalReviewEnabled' => DocumentShareService::enabled(),
            'canResetMfa' => (bool) auth()->user()?->isOwner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'organization_id' => ['nullable', 'integer'],
            'new_organization' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->reviewers->register($request->user(), $data['name'], $data['email'], $data['organization_id'] ?? null, $data['new_organization'] ?? null);
        } catch (PsychosocialDocumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Revisor cadastrado. '.($result['mailed'] ? 'O convite foi enviado por e-mail.' : 'O convite não pôde ser enviado por e-mail ('.$result['mail_error'].'); use «Reenviar convite».'));
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        try {
            $result = $this->reviewers->resend($request->user(), $user);
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $result['mailed']
            ? back()->with('success', 'Convite reenviado.')
            : back()->with('error', 'O convite não pôde ser enviado: '.$result['mail_error']);
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        try {
            $this->reviewers->setActive($request->user(), $user, (bool) $data['active']);
        } catch (PsychosocialDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $data['active'] ? 'Revisor reativado.' : 'Revisor desativado.');
    }
}
