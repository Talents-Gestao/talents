<?php

declare(strict_types=1);

namespace App\Http\Controllers\HiringFollowup;

use App\Enums\FollowupAudience;
use App\Http\Controllers\Controller;
use App\Models\HiringFollowupInvitation;
use App\Services\Hiring\FollowupResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pesquisa pública do pós-contratação (/pos-contratacao/{token}): sem login, uma resposta por convite.
 * Mostra só o mínimo para a pessoa responder; nunca dados nem respostas de outras pessoas.
 */
class PublicFollowupController extends Controller
{
    public function __construct(
        private readonly FollowupResponseService $responses,
    ) {}

    public function show(string $token): Response
    {
        $invitation = $this->find($token);
        $message = $this->responses->unavailableMessage($invitation);
        if ($message !== null) {
            return Inertia::render('HiringFollowup/Unavailable', ['message' => $message]);
        }

        $cycle = $invitation->milestone->cycle;
        $first = trim(explode(' ', trim($invitation->recipient_name))[0] ?? '');

        return Inertia::render('HiringFollowup/Take', [
            'survey' => [
                'token' => $invitation->token,
                'audience' => $invitation->audience->value,
                'greeting_name' => $first !== '' ? $first : null,
                'company_name' => $cycle->company?->name,
                'moment_label' => $invitation->milestone->moment->label(),
                // O gestor responde sobre o colaborador (só o nome); o colaborador, sobre a própria experiência.
                'subject_name' => $invitation->audience === FollowupAudience::Manager ? $cycle->employee?->name : null,
            ],
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'support_requested' => ['boolean'],
            'support_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'rating.required' => 'Escolha uma nota de 1 a 5 estrelas.',
            'rating.between' => 'Escolha uma nota de 1 a 5 estrelas.',
            'comment.max' => 'O comentário pode ter até 2.000 caracteres.',
        ], ['comment' => 'comentário', 'support_note' => 'pedido de apoio']);

        $this->responses->answer($invitation, $data);

        return redirect()->route('hiring-followup.public.thanks', $token);
    }

    public function thanks(string $token): Response|RedirectResponse
    {
        $invitation = $this->find($token);
        if ($invitation->answered_at === null) {
            return redirect()->route('hiring-followup.public', $token);
        }

        return Inertia::render('HiringFollowup/ThankYou');
    }

    private function find(string $token): HiringFollowupInvitation
    {
        return HiringFollowupInvitation::query()
            ->where('token', $token)
            ->with(['milestone.cycle.company:id,name', 'milestone.cycle.employee:id,name'])
            ->firstOrFail();
    }
}
