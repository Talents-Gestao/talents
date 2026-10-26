<?php

declare(strict_types=1);

namespace App\Services\Nr1;

use App\Exceptions\Nr1CampaignException;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyInvitationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Convite único por campanha (opção da Talents). O convite é um código aleatório mostrado UMA vez; no banco fica só o
 * hash, o público e o mês de uso. Nenhuma coluna liga o convite à resposta, à sessão, ao aparelho ou a uma pessoa.
 */
class Nr1InvitationService
{
    public const MAX_PER_BATCH = 5000;

    public function setEnabled(Survey $survey, User $actor, bool $enabled): void
    {
        $this->assertTalents($actor);
        $this->assertV11($survey);

        $survey->forceFill(['uses_invitations' => $enabled])->save();
    }

    /**
     * @return list<string> URLs completas com o código (única vez em que existem em claro)
     */
    public function generate(Survey $survey, User $actor, ?string $audience, int $count): array
    {
        $this->assertTalents($actor);
        $this->assertV11($survey);

        if ($count < 1 || $count > self::MAX_PER_BATCH) {
            throw new Nr1CampaignException('Informe uma quantidade entre 1 e '.self::MAX_PER_BATCH.'.');
        }

        $link = null;
        if ($survey->audienceLinks()->exists()) {
            $link = $survey->audienceLinks()->where('audience', $audience)->first();
            if ($link === null) {
                throw new Nr1CampaignException('A campanha tem links por público: escolha Time ou Líderes.');
            }
        } elseif ($audience !== null && ! in_array($audience, SurveyAudienceLink::AUDIENCES, true)) {
            throw new Nr1CampaignException('Público inválido.');
        }

        $base = route('survey.public', ['token' => $link?->token ?? $survey->public_token]);
        $urls = [];

        DB::transaction(function () use ($survey, $audience, $count, $base, &$urls): void {
            for ($i = 0; $i < $count; $i++) {
                $code = Str::random(32);
                SurveyInvitationToken::query()->create([
                    'survey_id' => $survey->id,
                    'audience' => $audience,
                    'token_hash' => SurveyInvitationToken::hashFor($code),
                ]);
                $urls[] = $base.'?convite='.$code;
            }
        });

        return $urls;
    }

    /**
     * Só contagens por público: nunca a lista de convites.
     *
     * @return list<array{audience: ?string, label: string, total: int, used: int, available: int}>
     */
    public function stats(Survey $survey): array
    {
        return SurveyInvitationToken::query()
            ->where('survey_id', $survey->id)
            ->selectRaw('audience, count(*) as total, sum(case when used_month is null then 0 else 1 end) as used')
            ->groupBy('audience')
            ->orderBy('audience')
            ->get()
            ->map(fn ($row): array => [
                'audience' => $row->audience,
                'label' => $row->audience !== null ? (SurveyAudienceLink::LABELS[$row->audience] ?? $row->audience) : 'Todos',
                'total' => (int) $row->total,
                'used' => (int) $row->used,
                'available' => (int) $row->total - (int) $row->used,
            ])
            ->all();
    }

    private function assertTalents(User $actor): void
    {
        if (! $actor->isSuperAdmin() && ! $actor->isOwner()) {
            throw new Nr1CampaignException('Só a Talents gerencia convites.');
        }
    }

    private function assertV11(Survey $survey): void
    {
        if (! $survey->usesReviewFlow()) {
            throw new Nr1CampaignException('O convite único vale para campanhas NR-1 V11.');
        }
    }
}
