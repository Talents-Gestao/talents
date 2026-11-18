<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Actions\Notices\PublishFeedbackNotice;
use App\Models\AiUsageLog;
use App\Models\Company;
use App\Services\Feedback\Ai\FeedbackAiException;
use Illuminate\Support\Carbon;

/**
 * Limite mensal de custo estimado de IA de Feedbacks por empresa (mês do calendário de São Paulo). Sem valor
 * na empresa, vale o padrão; zero bloqueia. Ao atingir, novos processamentos são recusados com mensagem clara
 * e a Talents recebe um aviso (um por empresa no mês).
 */
final class FeedbackAiMonthlyLimit
{
    public const DEFAULT_LIMIT_CENTS = 5000;

    public static function limitCents(Company $company): int
    {
        return $company->feedback_ai_monthly_limit_cents ?? self::DEFAULT_LIMIT_CENTS;
    }

    public static function monthStart(): Carbon
    {
        return now('America/Sao_Paulo')->startOfMonth()->utc();
    }

    public static function usedCents(int $companyId): int
    {
        return (int) AiUsageLog::query()
            ->where('company_id', $companyId)
            ->whereIn('purpose', AiUsageLog::FEEDBACK_PURPOSES)
            ->where('created_at', '>=', self::monthStart())
            ->sum('estimated_cost_cents');
    }

    public static function message(Company $company): string
    {
        return sprintf(
            'O limite mensal de IA de Feedbacks desta empresa foi atingido (%s de %s). A Talents foi avisada; o processamento volta no próximo mês ou quando o limite for ampliado.',
            self::money(self::usedCents($company->id)),
            self::money(self::limitCents($company)),
        );
    }

    /** @throws FeedbackAiException */
    public function assertWithin(Company $company): void
    {
        if (self::usedCents($company->id) < self::limitCents($company)) {
            return;
        }

        app(PublishFeedbackNotice::class)->aiLimitReached($company);

        throw new FeedbackAiException(self::message($company), 'monthly_limit');
    }

    public static function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
