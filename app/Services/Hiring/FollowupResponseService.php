<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Actions\Notices\PublishHiringFollowupNotice;
use App\Models\HiringFollowupInvitation;
use App\Models\HiringFollowupMilestone;
use App\Models\HiringFollowupResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resposta da pesquisa pública pós-contratação: uma por convite, só com convite válido.
 * A primeira resposta conclui o marco. Pedido de apoio gera aviso interno sem o texto da resposta.
 */
final class FollowupResponseService
{
    public function __construct(
        private readonly PublishHiringFollowupNotice $notice,
    ) {}

    /** Mensagem própria para convite que não pode mais ser respondido (null = pode). */
    public function unavailableMessage(HiringFollowupInvitation $invitation): ?string
    {
        return match (true) {
            $invitation->answered_at !== null => 'Esta pesquisa já foi respondida. Obrigado pela participação!',
            $invitation->revoked_at !== null => 'Este link foi substituído ou cancelado pela Talents. Se precisar, peça um novo link.',
            $invitation->expires_at->isPast() => 'Este link expirou. Se ainda quiser responder, peça um novo link à Talents.',
            default => null,
        };
    }

    /**
     * @param  array{rating: int, comment?: string|null, support_requested?: bool, support_note?: string|null}  $data
     */
    public function answer(HiringFollowupInvitation $invitation, array $data): HiringFollowupResponse
    {
        try {
            $response = DB::transaction(function () use ($invitation, $data): HiringFollowupResponse {
                $locked = HiringFollowupInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
                $message = $this->unavailableMessage($locked);
                if ($message !== null) {
                    throw ValidationException::withMessages(['rating' => $message]);
                }

                $support = (bool) ($data['support_requested'] ?? false);
                $response = HiringFollowupResponse::query()->create([
                    'invitation_id' => $locked->id,
                    'rating' => (int) $data['rating'],
                    'comment' => $this->clean($data['comment'] ?? null),
                    'support_requested' => $support,
                    'support_note' => $support ? $this->clean($data['support_note'] ?? null) : null,
                ]);

                $locked->update(['answered_at' => now()]);
                HiringFollowupMilestone::query()
                    ->whereKey($locked->milestone_id)
                    ->whereNull('completed_at')
                    ->update(['completed_at' => now()]);

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['rating' => 'Esta pesquisa já foi respondida. Obrigado pela participação!']);
        }

        if ($response->support_requested) {
            $this->notice->supportRequested($response);
        }

        return $response;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
