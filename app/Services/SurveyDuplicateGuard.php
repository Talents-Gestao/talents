<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyInvitationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Política de envio duplicado, atrás de config('nr1.collection.duplicate_policy').
 * Padrão `none`: nada de novo é coletado (só o limite de taxa existente). As demais opções preservam
 * o anonimato: nenhuma guarda o vínculo entre pessoa e resposta.
 */
final class SurveyDuplicateGuard
{
    public const POLICY_NONE = 'none';

    public const POLICY_DEVICE_COOKIE = 'device_cookie';

    public const POLICY_ONE_TIME_TOKEN = 'one_time_token';

    public const BLOCK_ALREADY_SENT = 'already_sent';

    public const BLOCK_INVITATION = 'invitation_required';

    public static function policy(): string
    {
        $policy = (string) config('nr1.collection.duplicate_policy', self::POLICY_NONE);

        return in_array($policy, [self::POLICY_NONE, self::POLICY_DEVICE_COOKIE, self::POLICY_ONE_TIME_TOKEN], true)
            ? $policy
            : self::POLICY_NONE;
    }

    /**
     * Política efetiva da campanha: a opção «convite único» da campanha vale sobre o padrão global (que segue `none`).
     */
    public static function policyFor(Survey $survey): string
    {
        return $survey->uses_invitations ? self::POLICY_ONE_TIME_TOKEN : self::policy();
    }

    public static function cookieName(Survey $survey): string
    {
        return 'nr1_sent_'.$survey->id;
    }

    /**
     * Antes de mostrar o questionário. Não consome nada.
     *
     * @return self::BLOCK_*|null
     */
    public static function blockReasonForView(Request $request, Survey $survey, ?SurveyAudienceLink $link, ?string $invitation): ?string
    {
        return match (self::policyFor($survey)) {
            self::POLICY_DEVICE_COOKIE => $request->cookies->has(self::cookieName($survey)) ? self::BLOCK_ALREADY_SENT : null,
            self::POLICY_ONE_TIME_TOKEN => self::findUsableInvitation($survey, $link, $invitation) === null ? self::BLOCK_INVITATION : null,
            default => null,
        };
    }

    /**
     * No envio. No convite de uso único, consome o convite (só o mês é registrado).
     * Deve rodar dentro da mesma transação que grava a resposta.
     *
     * @return self::BLOCK_*|null
     */
    public static function consume(Request $request, Survey $survey, ?SurveyAudienceLink $link, ?string $invitation): ?string
    {
        switch (self::policyFor($survey)) {
            case self::POLICY_DEVICE_COOKIE:
                return $request->cookies->has(self::cookieName($survey)) ? self::BLOCK_ALREADY_SENT : null;

            case self::POLICY_ONE_TIME_TOKEN:
                $token = self::findUsableInvitation($survey, $link, $invitation);
                if ($token === null) {
                    return self::BLOCK_INVITATION;
                }

                $consumed = SurveyInvitationToken::query()
                    ->whereKey($token->id)
                    ->whereNull('used_month')
                    ->update(['used_month' => now()->startOfMonth()->toDateString()]);

                return $consumed === 1 ? null : self::BLOCK_INVITATION;

            default:
                return null;
        }
    }

    /**
     * Depois de gravar a resposta: no modo de cookie, marca o navegador (sem identificador no servidor).
     */
    public static function markSent(Survey $survey): void
    {
        if (self::policyFor($survey) === self::POLICY_DEVICE_COOKIE) {
            Cookie::queue(Cookie::make(self::cookieName($survey), '1', 60 * 24 * 365));
        }
    }

    public static function message(string $reason): string
    {
        return match ($reason) {
            self::BLOCK_ALREADY_SENT => 'Já recebemos uma resposta enviada deste dispositivo. Obrigado pela participação.',
            default => 'Este endereço exige um convite válido. Use o link completo que você recebeu.',
        };
    }

    private static function findUsableInvitation(Survey $survey, ?SurveyAudienceLink $link, ?string $invitation): ?SurveyInvitationToken
    {
        if ($invitation === null || $invitation === '') {
            return null;
        }

        return SurveyInvitationToken::query()
            ->where('survey_id', $survey->id)
            ->where('token_hash', SurveyInvitationToken::hashFor($invitation))
            ->whereNull('used_month')
            ->where(fn ($q) => $q->whereNull('audience')->when($link !== null, fn ($q2) => $q2->orWhere('audience', $link->audience)))
            ->first();
    }
}
