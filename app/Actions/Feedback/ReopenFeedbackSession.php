<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Enums\FeedbackSessionStatus;
use App\Models\FeedbackAuditLog;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «Reabrir para edição» de um feedback aguardando assinaturas: cancela os convites, descarta as assinaturas já
 * coletadas (ficam no histórico como canceladas; os links antigos passam a recusar) e volta o feedback para
 * «Em preenchimento». A quantidade confirmada na tela precisa bater com a atual. Trilha sem conteúdo.
 */
class ReopenFeedbackSession
{
    public const CANCELLED_LINK_MESSAGE = 'Este pedido de assinatura foi cancelado porque o feedback foi reaberto para edição. Aguarde um novo convite por e-mail.';

    /**
     * @return array{invites: int, signed: int}
     */
    public function impact(FeedbackSession $session): array
    {
        $signatures = $session->signatures()->get();

        return ['invites' => $signatures->count(), 'signed' => $signatures->filter->isSigned()->count()];
    }

    /**
     * @return array{invites: int, signed: int}
     */
    public function execute(FeedbackSession $session, User $actor, int $confirmedSigned): array
    {
        return DB::transaction(function () use ($session, $actor, $confirmedSigned) {
            $locked = FeedbackSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== FeedbackSessionStatus::AwaitingSignatures) {
                throw ValidationException::withMessages([
                    'reopen' => 'Só é possível reabrir um feedback que está aguardando assinaturas.',
                ]);
            }

            $signatures = FeedbackSessionSignature::query()
                ->where('feedback_session_id', $locked->id)
                ->whereNull('cancelled_at')
                ->lockForUpdate()
                ->get();
            $signed = $signatures->filter->isSigned()->count();

            if ($signed !== $confirmedSigned) {
                throw ValidationException::withMessages([
                    'signed_count' => 'O número de assinaturas mudou desde que você abriu o aviso. Confira e confirme de novo.',
                ]);
            }

            FeedbackSessionSignature::query()
                ->whereIn('id', $signatures->pluck('id'))
                ->update(['cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id]);

            $locked->update(['status' => FeedbackSessionStatus::InProgress, 'completed_at' => null]);

            FeedbackAuditLog::query()->create([
                'company_id' => $locked->company_id,
                'user_id' => $actor->id,
                'action' => FeedbackAuditLog::ACTION_SESSION_REOPENED,
                'subject_type' => FeedbackAuditLog::SUBJECT_SESSION,
                'subject_id' => $locked->id,
                'subject_label' => $locked->title,
                'details' => [
                    'invites_cancelled' => $signatures->count(),
                    'signatures_discarded' => $signed,
                    'signature_ids' => $signatures->pluck('id')->values()->all(),
                ],
            ]);

            return ['invites' => $signatures->count(), 'signed' => $signed];
        });
    }
}
